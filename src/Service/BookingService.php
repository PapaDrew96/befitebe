<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\BookingRepository;
use Befit\Repository\NotificationRepository;
use Befit\Repository\ScheduleRepository;
use Befit\Repository\SettingRepository;
use Befit\Repository\UserRepository;
use Befit\Repository\WaitlistRepository;
use Befit\Support\InputValidator;
use DateTimeImmutable;
use DateTimeZone;

final class BookingService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly Database $database,
        private readonly BookingRepository $bookings,
        private readonly ScheduleRepository $schedule,
        private readonly UserRepository $users,
        private readonly SettingRepository $settings,
        private readonly WaitlistRepository $waitlist,
        private readonly NotificationRepository $notifications,
        private readonly ActivityLogRepository $activity,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function mine(int $userId, array $query): array
    {
        $today = new DateTimeImmutable('today', $this->timezone);
        $from = $this->validDate((string)($query['from'] ?? $today->modify('-30 days')->format('Y-m-d')), 'from');
        $to = $this->validDate((string)($query['to'] ?? $today->modify('+120 days')->format('Y-m-d')), 'to');
        if ($to < $from) throw ApiException::validation(['to'=>['The end date must be on or after the start date.']]);
        return array_map([$this,'normalize'],$this->bookings->mine($userId,$from->format('Y-m-d'),$to->format('Y-m-d')));
    }

    public function createMember(array $input, int $actorUserId): array
    {
        (new InputValidator($input))->requiredInt('session_id',1)->throwIfInvalid();
        return $this->book((int)$input['session_id'],$actorUserId,$actorUserId,false);
    }

    public function createAdmin(array $input, int $actorUserId): array
    {
        (new InputValidator($input))->requiredInt('session_id',1)->requiredInt('user_id',1)->throwIfInvalid();
        return $this->book((int)$input['session_id'],(int)$input['user_id'],$actorUserId,true);
    }

    public function joinWaitlist(array $input, int $userId): array
    {
        (new InputValidator($input))->requiredInt('session_id',1)->throwIfInvalid();
        if (!$this->settingBool('waitlist_enabled', true)) throw ApiException::conflict('The waiting list is disabled.');
        $sessionId=(int)$input['session_id'];

        $entryId=$this->database->transaction(function() use($sessionId,$userId): int {
            $session=$this->schedule->findSessionForUpdate($sessionId);
            if(!$session) throw ApiException::notFound('Session not found.');
            $this->assertMemberMayTargetSession($session,$userId);
            $existingBooking=$this->bookings->findBySessionAndUser($sessionId,$userId);
            if($existingBooking&&in_array($existingBooking['status'],['booked','checked_in'],true)) throw ApiException::conflict('You already have a booking for this session.');
            if($this->bookings->countBookedForSession($sessionId) < (int)$session['capacity']) throw ApiException::conflict('This session still has an available place. Book it directly instead.');
            $existing=$this->waitlist->findBySessionAndUser($sessionId,$userId);
            if($existing && $existing['status']==='waiting') throw ApiException::conflict('You are already on the waiting list for this session.');
            $id=$this->waitlist->join($sessionId,$userId);
            $this->activity->log($userId,'waitlist.join','waitlist',$id,['session_id'=>$sessionId]);
            return $id;
        });

        $entry=$this->waitlist->findBySessionAndUser($sessionId,$userId);
        return ['id'=>$entryId,'session_id'=>$sessionId,'status'=>$entry['status'] ?? 'waiting','position'=>$this->waitlist->countWaitingForSession($sessionId)];
    }

    public function leaveWaitlist(int $waitlistId, int $userId): void
    {
        $found=$this->waitlist->findByIdForUser($waitlistId,$userId);
        if(!$found) throw ApiException::notFound('Waiting-list entry not found.');
        if($found['status']!=='waiting') throw ApiException::conflict('This waiting-list entry is no longer active.');
        $this->database->transaction(function() use($waitlistId,$userId,$found): void {
            $this->waitlist->leave($waitlistId);
            $this->activity->log($userId,'waitlist.leave','waitlist',$waitlistId,['session_id'=>(int)$found['session_id']]);
        });
    }

    public function cancelMember(int $bookingId,int $actorUserId): void
    {
        $booking=$this->bookings->findDetailed($bookingId);
        if(!$booking) throw ApiException::notFound('Booking not found.');
        if((int)$booking['user_id']!==$actorUserId) throw ApiException::forbidden('You can only cancel your own booking.');
        $this->cancel($booking,$actorUserId,false);
    }

    public function cancelAdmin(int $bookingId,int $actorUserId): void
    {
        $booking=$this->bookings->findDetailed($bookingId);
        if(!$booking) throw ApiException::notFound('Booking not found.');
        $this->cancel($booking,$actorUserId,true);
    }

    public function markAttendance(int $bookingId,array $input,int $actorUserId): array
    {
        (new InputValidator($input))->oneOf('status',['booked','checked_in','no_show'])->throwIfInvalid();
        $status=(string)($input['status'] ?? '');
        if($status==='') throw ApiException::validation(['status'=>['Status is required.']]);
        $booking=$this->bookings->findDetailed($bookingId);
        if(!$booking) throw ApiException::notFound('Booking not found.');
        if($booking['status']==='cancelled') throw ApiException::conflict('Cancelled bookings cannot be marked for attendance.');
        $checkedInAt=$status==='checked_in'?(new DateTimeImmutable('now',$this->timezone))->format('Y-m-d H:i:s'):null;
        $this->database->transaction(function() use($bookingId,$status,$actorUserId,$checkedInAt,$booking): void {
            $this->bookings->markAttendance($bookingId,$status,$actorUserId,$checkedInAt);
            $this->activity->log($actorUserId,'booking.attendance','booking',$bookingId,['status'=>$status,'user_id'=>(int)$booking['user_id']]);
        });
        return $this->normalize($this->bookings->findDetailed($bookingId) ?? $booking);
    }

    public function adminList(array $query): array
    {
        $page=max(1,(int)($query['page']??1));$perPage=min(100,max(1,(int)($query['per_page']??25)));
        $filters=['from'=>$query['from']??null,'to'=>$query['to']??null,'status'=>$query['status']??null,'user_id'=>$query['user_id']??null,'session_id'=>$query['session_id']??null,'search'=>trim((string)($query['search']??''))];
        if($filters['from'])$this->validDate((string)$filters['from'],'from');
        if($filters['to'])$this->validDate((string)$filters['to'],'to');
        if($filters['status']&&!in_array($filters['status'],['booked','checked_in','no_show','cancelled'],true))throw ApiException::validation(['status'=>['Invalid booking status.']]);
        if($filters['user_id']!==null&&filter_var($filters['user_id'],FILTER_VALIDATE_INT)===false)throw ApiException::validation(['user_id'=>['Must be an integer.']]);
        if($filters['session_id']!==null&&filter_var($filters['session_id'],FILTER_VALIDATE_INT)===false)throw ApiException::validation(['session_id'=>['Must be an integer.']]);
        $result=$this->bookings->adminList($filters,$page,$perPage);
        return ['items'=>array_map([$this,'normalize'],$result['items']),'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$result['total'],'pages'=>(int)ceil($result['total']/$perPage)]];
    }

    public function adminWaitlist(array $query): array
    {
        $today = new DateTimeImmutable('today', $this->timezone);
        $from = $this->validDate((string)($query['from'] ?? $today->format('Y-m-d')), 'from');
        $to = $this->validDate((string)($query['to'] ?? $today->modify('+30 days')->format('Y-m-d')), 'to');
        if ($to < $from) throw ApiException::validation(['to'=>['The end date must be on or after the start date.']]);
        if ($from->diff($to)->days > 120) throw ApiException::validation(['to'=>['The waiting-list range may not exceed 120 days.']]);
        $status = trim((string)($query['status'] ?? 'waiting'));
        if ($status !== '' && !in_array($status, ['waiting','promoted','left'], true)) {
            throw ApiException::validation(['status'=>['Invalid waiting-list status.']]);
        }
        $rows = $this->waitlist->adminList($from->format('Y-m-d'), $to->format('Y-m-d'), $status);
        return array_map(static fn(array $row): array => [
            'id'=>(int)$row['id'],
            'session_id'=>(int)$row['session_id'],
            'user_id'=>(int)$row['user_id'],
            'status'=>$row['status'],
            'joined_at'=>$row['joined_at'],
            'left_at'=>$row['left_at'],
            'promoted_at'=>$row['promoted_at'],
            'promoted_booking_id'=>$row['promoted_booking_id'] !== null ? (int)$row['promoted_booking_id'] : null,
            'date'=>$row['session_date'],
            'start_time'=>substr($row['start_time'],0,5),
            'end_time'=>!empty($row['end_time']) ? substr($row['end_time'],0,5) : null,
            'session_status'=>$row['session_status'],
            'member'=>[
                'id'=>(int)$row['user_id'],
                'first_name'=>$row['first_name'],
                'last_name'=>$row['last_name'],
                'display_name'=>trim($row['first_name'].' '.$row['last_name']),
                'email'=>$row['email'],
                'phone'=>$row['phone'],
            ],
        ], $rows);
    }

    private function book(int $sessionId,int $targetUserId,int $actorUserId,bool $adminOverride): array
    {
        $target=$this->users->findById($targetUserId);
        if(!$target)throw ApiException::notFound('User not found.');
        if($target['status']!=='active')throw ApiException::conflict('The selected member account is inactive.');

        $bookingId=$this->database->transaction(function() use($sessionId,$targetUserId,$actorUserId,$adminOverride): int {
            $session=$this->schedule->findSessionForUpdate($sessionId);
            if(!$session)throw ApiException::notFound('Session not found.');
            if($session['status']!=='open')throw ApiException::conflict('This session is not open for bookings.');
            if($this->schedule->findClosureByDate($session['session_date']))throw ApiException::conflict('The gym is closed on this date.');
            $startsAt=new DateTimeImmutable($session['session_date'].' '.$session['start_time'],$this->timezone);
            if($startsAt<=new DateTimeImmutable('now',$this->timezone))throw ApiException::conflict('Past sessions cannot be booked.');
            $existing=$this->bookings->findBySessionAndUser($sessionId,$targetUserId);
            if($existing&&in_array($existing['status'],['booked','checked_in'],true))throw ApiException::conflict('This member is already booked for the session.');
            if(!$adminOverride)$this->assertMemberBookingRules($session,$targetUserId,$startsAt);
            if($this->bookings->countBookedForSession($sessionId)>=(int)$session['capacity'])throw ApiException::conflict('This session is already full.');
            $id=$this->bookings->upsertBooked($sessionId,$targetUserId,$actorUserId);
            $waiting=$this->waitlist->findBySessionAndUser($sessionId,$targetUserId);
            if($waiting&&$waiting['status']==='waiting')$this->waitlist->markPromoted((int)$waiting['id'],$id);
            $this->notifications->create($targetUserId,'booking_confirmed','Booking confirmed','Your training place has been reserved.',['booking_id'=>$id,'session_id'=>$sessionId]);
            $this->activity->log($actorUserId,'booking.create','booking',$id,['session_id'=>$sessionId,'user_id'=>$targetUserId,'admin_override'=>$adminOverride]);
            return $id;
        });
        $booking=$this->bookings->findDetailed($bookingId);
        if(!$booking)throw ApiException::notFound('Booking not found after creation.');
        return $this->normalize($booking);
    }

    private function cancel(array $booking,int $actorUserId,bool $allowPast): void
    {
        if(!in_array($booking['status'],['booked','checked_in'],true))throw ApiException::conflict('This booking cannot be cancelled.');
        $startsAt=new DateTimeImmutable($booking['session_date'].' '.$booking['start_time'],$this->timezone);
        $now=new DateTimeImmutable('now',$this->timezone);
        if(!$allowPast){
            if($startsAt<=$now)throw ApiException::conflict('Past bookings cannot be cancelled by a member.');
            $cutoff=$this->settingInt('cancellation_cutoff_minutes',120);
            if($cutoff>0&&$now>$startsAt->modify('-'.$cutoff.' minutes'))throw ApiException::conflict('Cancellations close '.$cutoff.' minutes before the session starts. Please contact the gym.');
        }

        $this->database->transaction(function() use($booking,$actorUserId,$now): void {
            if (!$this->schedule->findSessionForUpdate((int)$booking['session_id'])) {
                throw ApiException::notFound('Session not found.');
            }
            $this->bookings->cancel((int)$booking['id'],$actorUserId,$now->format('Y-m-d H:i:s'));
            $this->notifications->create((int)$booking['user_id'],'booking_cancelled','Booking cancelled','Your training booking was cancelled.',['booking_id'=>(int)$booking['id'],'session_id'=>(int)$booking['session_id']]);
            $this->activity->log($actorUserId,'booking.cancel','booking',(int)$booking['id'],['user_id'=>(int)$booking['user_id'],'session_id'=>(int)$booking['session_id']]);
            if($this->settingBool('waitlist_enabled',true)&&$this->settingBool('auto_promote_waitlist',true))$this->promoteNextWaitlisted((int)$booking['session_id'],$actorUserId);
        });
    }

    private function promoteNextWaitlisted(int $sessionId,int $actorUserId): void
    {
        $session=$this->schedule->findSessionForUpdate($sessionId);
        if(!$session||$session['status']!=='open')return;
        while($this->bookings->countBookedForSession($sessionId)<(int)$session['capacity']){
            $entry=$this->waitlist->nextWaitingForUpdate($sessionId);
            if(!$entry)return;
            if($entry['user_status']!=='active'){$this->waitlist->leave((int)$entry['id']);continue;}
            $existing=$this->bookings->findBySessionAndUser($sessionId,(int)$entry['user_id']);
            if($existing&&in_array($existing['status'],['booked','checked_in'],true)){$this->waitlist->markPromoted((int)$entry['id'],(int)$existing['id']);continue;}
            $bookingId=$this->bookings->upsertBooked($sessionId,(int)$entry['user_id'],$actorUserId);
            $this->waitlist->markPromoted((int)$entry['id'],$bookingId);
            $this->notifications->create((int)$entry['user_id'],'waitlist_promoted','A place became available','You were automatically moved from the waiting list into the session.',['booking_id'=>$bookingId,'session_id'=>$sessionId]);
            $this->activity->log($actorUserId,'waitlist.promote','booking',$bookingId,['waitlist_id'=>(int)$entry['id'],'user_id'=>(int)$entry['user_id']]);
            return;
        }
    }

    private function assertMemberMayTargetSession(array $session,int $userId): void
    {
        if($session['status']!=='open')throw ApiException::conflict('This session is not open.');
        if($this->schedule->findClosureByDate($session['session_date']))throw ApiException::conflict('The gym is closed on this date.');
        $startsAt=new DateTimeImmutable($session['session_date'].' '.$session['start_time'],$this->timezone);
        $now=new DateTimeImmutable('now',$this->timezone);
        if($startsAt<=$now)throw ApiException::conflict('Past sessions cannot be selected.');
        $this->assertMemberBookingRules($session,$userId,$startsAt);
    }

    private function assertMemberBookingRules(array $session,int $userId,DateTimeImmutable $startsAt): void
    {
        $now=new DateTimeImmutable('now',$this->timezone);
        $daysAhead=$this->settingInt('booking_days_ahead',30);
        if($daysAhead>0&&$startsAt>$now->modify('+'.$daysAhead.' days'))throw ApiException::conflict('Bookings open only '.$daysAhead.' days in advance.');
        $cutoff=$this->settingInt('booking_cutoff_minutes',60);
        if($cutoff>0&&$now>$startsAt->modify('-'.$cutoff.' minutes'))throw ApiException::conflict('Booking closes '.$cutoff.' minutes before the session starts.');
    }


    private function normalize(array $row): array
    {
        $result=['id'=>(int)$row['id'],'session_id'=>(int)$row['session_id'],'user_id'=>(int)$row['user_id'],'status'=>$row['status'],'date'=>$row['session_date'],'start_time'=>substr($row['start_time'],0,5),'end_time'=>!empty($row['end_time'])?substr($row['end_time'],0,5):null,'session_status'=>$row['session_status']??null,'created_at'=>$row['created_at']??null,'cancelled_at'=>$row['cancelled_at']??null,'checked_in_at'=>$row['checked_in_at']??null];
        if(array_key_exists('first_name',$row))$result['member']=['id'=>(int)$row['user_id'],'first_name'=>$row['first_name'],'last_name'=>$row['last_name'],'display_name'=>trim($row['first_name'].' '.$row['last_name']),'email'=>$row['email']??null,'phone'=>$row['phone']??null];
        return $result;
    }

    private function settingInt(string $key,int $default): int { return max(0,(int)($this->settings->get($key,(string)$default)??$default)); }
    private function settingBool(string $key,bool $default): bool { return filter_var($this->settings->get($key,$default?'1':'0'),FILTER_VALIDATE_BOOL); }
    private function validDate(string $value,string $field): DateTimeImmutable { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$this->timezone);if(!$d||$d->format('Y-m-d')!==$value)throw ApiException::validation([$field=>['Must be a date in YYYY-MM-DD format.']]);return $d; }
}
