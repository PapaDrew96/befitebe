<?php

declare(strict_types=1);

namespace Befit\Service;

use DateTimeImmutable;

final class NotificationContentService
{
    public function format(
        string $type,
        string $fallbackTitle,
        string $fallbackBody,
        array $data,
        string $language
    ): array {
        $language = $language === 'el' ? 'el' : 'en';
        $slot = $this->slot($data, $language);
        $title = $fallbackTitle;
        $body = $fallbackBody;

        switch ($type) {
            case 'booking_confirmed':
                $title = $language === 'el'
                    ? 'Η κράτησή σου επιβεβαιώθηκε'
                    : 'Booking confirmed';
                $body = $language === 'el'
                    ? ($slot !== '' ? $slot . '. Η θέση σου έχει κρατηθεί.' : 'Η θέση σου έχει κρατηθεί.')
                    : ($slot !== '' ? $slot . '. Your training place has been reserved.' : 'Your training place has been reserved.');
                break;

            case 'booking_cancelled':
                $title = $language === 'el'
                    ? 'Η κράτησή σου ακυρώθηκε'
                    : 'Booking cancelled';
                $body = $language === 'el'
                    ? ($slot !== '' ? $slot . '. Η κράτηση δεν είναι πλέον ενεργή.' : 'Η κράτηση δεν είναι πλέον ενεργή.')
                    : ($slot !== '' ? $slot . '. This booking is no longer active.' : 'This booking is no longer active.');
                break;

            case 'waitlist_promoted':
                $title = $language === 'el'
                    ? 'Βρέθηκε θέση!'
                    : 'A place became available';
                $body = $language === 'el'
                    ? ($slot !== '' ? $slot . '. Μεταφέρθηκες αυτόματα από τη λίστα αναμονής στην προπόνηση.' : 'Μεταφέρθηκες αυτόματα από τη λίστα αναμονής στην προπόνηση.')
                    : ($slot !== '' ? $slot . '. You were automatically moved from the waiting list into the session.' : 'You were automatically moved from the waiting list into the session.');
                break;

            case 'session_changed':
                $status = (string)($data['status'] ?? '');
                if ($status === 'cancelled') {
                    $title = $language === 'el' ? 'Η προπόνησή σου ακυρώθηκε' : 'Training session cancelled';
                    $body = $language === 'el'
                        ? ($slot !== '' ? $slot . '. Η προπόνηση ακυρώθηκε. Έλεγξε τις κρατήσεις σου.' : 'Η προπόνηση ακυρώθηκε. Έλεγξε τις κρατήσεις σου.')
                        : ($slot !== '' ? $slot . '. The session was cancelled. Please review your bookings.' : 'The session was cancelled. Please review your bookings.');
                } elseif ($status === 'closed') {
                    $title = $language === 'el' ? 'Η προπόνηση έκλεισε' : 'Training session closed';
                    $body = $language === 'el'
                        ? ($slot !== '' ? $slot . '. Η προπόνηση δεν είναι πλέον ανοικτή. Έλεγξε την κράτησή σου.' : 'Η προπόνηση δεν είναι πλέον ανοικτή. Έλεγξε την κράτησή σου.')
                        : ($slot !== '' ? $slot . '. The session is no longer open. Please review your booking.' : 'The session is no longer open. Please review your booking.');
                } else {
                    $title = $language === 'el' ? 'Άλλαξε η προπόνησή σου' : 'Training session updated';
                    $body = $language === 'el'
                        ? ($slot !== '' ? 'Νέα ημερομηνία/ώρα: ' . $slot . '. Έλεγξε την κράτησή σου.' : 'Η ημερομηνία ή η ώρα της προπόνησης άλλαξε. Έλεγξε την κράτησή σου.')
                        : ($slot !== '' ? 'New date/time: ' . $slot . '. Please review your booking.' : 'The session date or time changed. Please review your booking.');
                }
                break;

            case 'waitlist_released':
                $title = $language === 'el' ? 'Η λίστα αναμονής έκλεισε' : 'Waiting list closed';
                $body = $language === 'el'
                    ? ($slot !== '' ? $slot . '. Η εγγραφή σου στη λίστα αναμονής αφαιρέθηκε.' : 'Η εγγραφή σου στη λίστα αναμονής αφαιρέθηκε.')
                    : ($slot !== '' ? $slot . '. Your waiting-list entry was released.' : 'Your waiting-list entry was released.');
                break;

            case 'gym_closed':
                $title = $language === 'el' ? 'Το γυμναστήριο θα είναι κλειστό' : 'Gym closed';
                $body = $language === 'el'
                    ? ($slot !== '' ? $slot . '. Η κράτησή σου ακυρώθηκε.' : 'Η κράτησή σου ακυρώθηκε.')
                    : ($slot !== '' ? $slot . '. Your reservation has been cancelled.' : 'Your reservation has been cancelled.');
                if (!empty($data['reason'])) {
                    $body .= ' ' . trim((string)$data['reason']);
                }
                break;

            case 'payment_confirmed':
                $title = $language === 'el' ? 'Η πληρωμή καταχωρήθηκε' : 'Payment confirmed';
                if (!empty($data['valid_until'])) {
                    $validUntil = $this->date((string)$data['valid_until'], $language);
                    $body = $language === 'el'
                        ? 'Η συνδρομή σου ισχύει έως ' . $validUntil . '.'
                        : 'Your membership is valid until ' . $validUntil . '.';
                }
                break;

            case 'session_reminder':
                $title = $language === 'el' ? 'Υπενθύμιση προπόνησης' : 'Training reminder';
                $body = $language === 'el'
                    ? ($slot !== '' ? 'Η προπόνησή σου είναι ' . $slot . '.' : 'Έχεις προγραμματισμένη προπόνηση.')
                    : ($slot !== '' ? 'Your training session is ' . $slot . '.' : 'You have an upcoming training session.');
                break;
        }

        return [
            'title' => $title,
            'body' => $body,
            'route' => $this->route($type, $data),
        ];
    }

    private function route(string $type, array $data): string
    {
        if (!empty($data['route']) && preg_match('/^#\/(bookings|schedule|payments|notifications)(?:\?|$)/', (string)$data['route'])) {
            return (string)$data['route'];
        }

        if (!empty($data['booking_id'])) {
            return '#/bookings?booking_id=' . rawurlencode((string)$data['booking_id']);
        }

        if ($type === 'payment_confirmed' && !empty($data['payment_id'])) {
            return '#/payments?payment_id=' . rawurlencode((string)$data['payment_id']);
        }

        if (!empty($data['session_id'])) {
            $route = '#/schedule?session_id=' . rawurlencode((string)$data['session_id']);
            if (!empty($data['date'])) {
                $route .= '&date=' . rawurlencode((string)$data['date']);
            }
            return $route;
        }

        return '#/notifications';
    }

    private function slot(array $data, string $language): string
    {
        $parts = [];

        if (!empty($data['date'])) {
            $parts[] = $this->date((string)$data['date'], $language);
        }

        if (!empty($data['start_time'])) {
            $start = $this->time((string)$data['start_time']);
            $end = !empty($data['end_time']) ? $this->time((string)$data['end_time']) : '';
            $parts[] = $end !== '' ? $start . '–' . $end : $start;
        }

        return implode(' · ', $parts);
    }

    private function date(string $value, string $language): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date) {
            return $value;
        }

        return $language === 'el'
            ? $date->format('d/m/Y')
            : $date->format('d/m/Y');
    }

    private function time(string $value): string
    {
        return substr($value, 0, 5);
    }
}
