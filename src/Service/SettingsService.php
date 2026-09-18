<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\SettingRepository;

final class SettingsService
{
    private const ALLOWED = [
        'gym_name', 'default_capacity', 'show_attendee_names',
        'booking_days_ahead', 'booking_cutoff_minutes', 'cancellation_cutoff_minutes',
        'waitlist_enabled', 'auto_promote_waitlist'
    ];

    public function __construct(
        private readonly Database $database,
        private readonly SettingRepository $settings,
        private readonly ActivityLogRepository $activity
    ) {}

    public function all(): array
    {
        $v = $this->settings->all();
        return [
            'gym_name' => $v['gym_name'] ?? 'BE-FIT TRAINING CENTER',
            'default_capacity' => (int)($v['default_capacity'] ?? 8),
            'show_attendee_names' => $this->bool($v['show_attendee_names'] ?? '0'),
            'booking_days_ahead' => (int)($v['booking_days_ahead'] ?? 30),
            'booking_cutoff_minutes' => (int)($v['booking_cutoff_minutes'] ?? 60),
            'cancellation_cutoff_minutes' => (int)($v['cancellation_cutoff_minutes'] ?? 120),
            'waitlist_enabled' => $this->bool($v['waitlist_enabled'] ?? '1'),
            'auto_promote_waitlist' => $this->bool($v['auto_promote_waitlist'] ?? '1'),
        ];
    }

    public function update(array $input, int $actorUserId): array
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED);
        if ($unknown !== []) throw ApiException::validation(['settings'=>['Unknown setting(s): '.implode(', ',$unknown)]]);

        $normalized=[];
        if(array_key_exists('gym_name',$input)){
            if(!is_string($input['gym_name'])||mb_strlen(trim($input['gym_name']))<1||mb_strlen(trim($input['gym_name']))>180) throw ApiException::validation(['gym_name'=>['Must contain between 1 and 180 characters.']]);
            $normalized['gym_name']=trim($input['gym_name']);
        }
        $intRules=[
            'default_capacity'=>[1,100],
            'booking_days_ahead'=>[0,365],
            'booking_cutoff_minutes'=>[0,10080],
            'cancellation_cutoff_minutes'=>[0,10080],
        ];
        foreach($intRules as $key=>[$min,$max]){
            if(!array_key_exists($key,$input))continue;
            $value=filter_var($input[$key],FILTER_VALIDATE_INT);
            if($value===false||$value<$min||$value>$max) throw ApiException::validation([$key=>["Must be an integer between {$min} and {$max}."]]);
            $normalized[$key]=(string)$value;
        }
        foreach(['show_attendee_names','waitlist_enabled','auto_promote_waitlist'] as $key){
            if(!array_key_exists($key,$input))continue;
            if(!is_bool($input[$key])&&!in_array($input[$key],[0,1,'0','1'],true)) throw ApiException::validation([$key=>['Must be a boolean.']]);
            $normalized[$key]=filter_var($input[$key],FILTER_VALIDATE_BOOL)?'1':'0';
        }

        $this->database->transaction(function() use($normalized,$actorUserId): void {
            foreach($normalized as $key=>$value)$this->settings->set($key,$value);
            $this->activity->log($actorUserId,'settings.update','settings',null,['fields'=>array_keys($normalized)]);
        });
        return $this->all();
    }

    private function bool(string $value): bool { return filter_var($value,FILTER_VALIDATE_BOOL); }
}
