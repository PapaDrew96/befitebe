<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class BookingRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findBySessionAndUser(int $sessionId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bookings WHERE session_id=:session_id AND user_id=:user_id LIMIT 1');
        $stmt->execute(['session_id'=>$sessionId,'user_id'=>$userId]);
        return $stmt->fetch() ?: null;
    }

    public function findDetailed(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT b.*, s.session_date, s.start_time, s.end_time, s.status AS session_status,
                    u.first_name,u.last_name,u.email,u.phone
             FROM bookings b
             INNER JOIN gym_sessions s ON s.id=b.session_id
             INNER JOIN users u ON u.id=b.user_id
             WHERE b.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public function countBookedForSession(int $sessionId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE session_id=:session_id AND status IN ('booked','checked_in')");
        $stmt->execute(['session_id'=>$sessionId]);
        return (int)$stmt->fetchColumn();
    }

    public function countFutureActiveForUser(int $userId, string $nowDateTime): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id
            WHERE b.user_id=:user_id AND b.status IN ('booked','checked_in')
            AND TIMESTAMP(s.session_date,s.start_time)>:now_dt");
        $stmt->execute(['user_id'=>$userId,'now_dt'=>$nowDateTime]);
        return (int)$stmt->fetchColumn();
    }

    public function countActiveForUserDate(int $userId, string $date): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id
            WHERE b.user_id=:user_id AND s.session_date=:session_date AND b.status IN ('booked','checked_in')");
        $stmt->execute(['user_id'=>$userId,'session_date'=>$date]);
        return (int)$stmt->fetchColumn();
    }

    public function upsertBooked(int $sessionId, int $userId, int $bookedByUserId): int
    {
        $existing = $this->findBySessionAndUser($sessionId,$userId);
        if ($existing) {
            $stmt = $this->pdo->prepare("UPDATE bookings SET status='booked', booked_by_user_id=:booked_by_user_id,
                cancelled_by_user_id=NULL,cancelled_at=NULL,checked_in_at=NULL,attendance_marked_by_user_id=NULL WHERE id=:id");
            $stmt->execute(['booked_by_user_id'=>$bookedByUserId,'id'=>$existing['id']]);
            return (int)$existing['id'];
        }
        $stmt = $this->pdo->prepare("INSERT INTO bookings (session_id,user_id,status,booked_by_user_id) VALUES (:session_id,:user_id,'booked',:booked_by_user_id)");
        $stmt->execute(['session_id'=>$sessionId,'user_id'=>$userId,'booked_by_user_id'=>$bookedByUserId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function cancel(int $bookingId, int $cancelledByUserId, string $cancelledAt): void
    {
        $stmt = $this->pdo->prepare("UPDATE bookings SET status='cancelled',cancelled_by_user_id=:cancelled_by_user_id,cancelled_at=:cancelled_at WHERE id=:id");
        $stmt->execute(['cancelled_by_user_id'=>$cancelledByUserId,'cancelled_at'=>$cancelledAt,'id'=>$bookingId]);
    }

    public function markAttendance(int $bookingId, string $status, int $actorUserId, ?string $checkedInAt): void
    {
        $stmt = $this->pdo->prepare("UPDATE bookings SET status=:status, checked_in_at=:checked_in_at, attendance_marked_by_user_id=:actor WHERE id=:id");
        $stmt->execute(['status'=>$status,'checked_in_at'=>$checkedInAt,'actor'=>$actorUserId,'id'=>$bookingId]);
    }


    public function activeUsersForSession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare("SELECT b.id AS booking_id, b.user_id
            FROM bookings b
            WHERE b.session_id=:session_id AND b.status IN ('booked','checked_in')
            ORDER BY b.id");
        $stmt->execute(['session_id'=>$sessionId]);
        return $stmt->fetchAll();
    }

    public function activeBookingsForDate(string $date): array
    {
        $stmt = $this->pdo->prepare("SELECT b.id AS booking_id, b.user_id, b.session_id, s.start_time
            FROM bookings b
            INNER JOIN gym_sessions s ON s.id=b.session_id
            WHERE s.session_date=:session_date AND b.status IN ('booked','checked_in')
            ORDER BY s.start_time, b.id");
        $stmt->execute(['session_date'=>$date]);
        return $stmt->fetchAll();
    }

    public function attendeesForSessions(array $sessionIds): array
    {
        if ($sessionIds === []) return [];
        $placeholders = implode(',',array_fill(0,count($sessionIds),'?'));
        $stmt = $this->pdo->prepare("SELECT b.id AS booking_id,b.session_id,b.status,u.id AS user_id,u.first_name,u.last_name
            FROM bookings b INNER JOIN users u ON u.id=b.user_id
            WHERE b.status IN ('booked','checked_in') AND b.session_id IN ({$placeholders})
            ORDER BY u.first_name,u.last_name");
        foreach(array_values($sessionIds) as $i=>$id) $stmt->bindValue($i+1,(int)$id,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function mine(int $userId,string $from,string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT b.*,s.session_date,s.start_time,s.end_time,s.status AS session_status
            FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id
            WHERE b.user_id=:user_id AND s.session_date BETWEEN :from_date AND :to_date
            ORDER BY s.session_date,s.start_time");
        $stmt->execute(['user_id'=>$userId,'from_date'=>$from,'to_date'=>$to]);
        return $stmt->fetchAll();
    }

    public function adminList(array $filters,int $page,int $perPage): array
    {
        $where=[];$params=[];
        if(!empty($filters['from'])){$where[]='s.session_date>=:from_date';$params['from_date']=$filters['from'];}
        if(!empty($filters['to'])){$where[]='s.session_date<=:to_date';$params['to_date']=$filters['to'];}
        if(!empty($filters['status'])){$where[]='b.status=:status';$params['status']=$filters['status'];}
        if(!empty($filters['user_id'])){$where[]='b.user_id=:user_id';$params['user_id']=(int)$filters['user_id'];}
        if(!empty($filters['session_id'])){$where[]='b.session_id=:session_id';$params['session_id']=(int)$filters['session_id'];}
        if(!empty($filters['search'])){$where[]='(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)';$params['search']='%'.$filters['search'].'%';}
        $whereSql=$where===[]?'':' WHERE '.implode(' AND ',$where);
        $base=' FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id INNER JOIN users u ON u.id=b.user_id';
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$base.$whereSql);$count->execute($params);$total=(int)$count->fetchColumn();
        $offset=($page-1)*$perPage;
        $stmt=$this->pdo->prepare('SELECT b.*,s.session_date,s.start_time,s.end_time,s.status AS session_status,u.first_name,u.last_name,u.email,u.phone'.$base.$whereSql.' ORDER BY s.session_date DESC,s.start_time DESC LIMIT :limit OFFSET :offset');
        foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v);
        $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
        return ['items'=>$stmt->fetchAll(),'total'=>$total];
    }
}
