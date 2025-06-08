<?php
/**
 * Pomocné funkce pro práci s daty a časy
 */
class MomDateHelper {

    /**
     * Vygeneruje pole datumů lekcí na základě startovního data a dne v týdnu
     */
    public function generate_lesson_dates($start_date, $day_of_week, $lesson_count) {
        $dates = [];
        $current_date = new DateTime($start_date);

        // Najdi první správný den v týdnu
        while ($current_date->format('N') != $day_of_week) {
            $current_date->add(new DateInterval('P1D'));
        }

        // Vygeneruj datumy lekcí
        for ($i = 0; $i < $lesson_count; $i++) {
            $dates[] = $current_date->format('Y-m-d');
            $current_date->add(new DateInterval('P7D')); // Přidat týden
        }

        return $dates;
    }

    /**
     * Formátuje datum pro zobrazení v češtině
     */
    public function format_czech_date($date, $include_time = false) {
        $timestamp = is_string($date) ? strtotime($date) : $date;

        if ($include_time) {
            return date('d.m.Y H:i', $timestamp);
        }

        return date('d.m.Y', $timestamp);
    }

    /**
     * Vrátí název dne v týdnu v češtině
     */
    public function get_czech_day_name($day_number) {
        $days = [
            1 => 'Pondělí',
            2 => 'Úterý',
            3 => 'Středa',
            4 => 'Čtvrtek',
            5 => 'Pátek',
            6 => 'Sobota',
            7 => 'Neděle'
        ];

        return $days[$day_number] ?? '';
    }

    /**
     * Zkontroluje, zda je datum v budoucnosti
     */
    public function is_future_date($date) {
        $timestamp = is_string($date) ? strtotime($date) : $date;
        return $timestamp > time();
    }

    /**
     * Vypočítá věk v měsících na základě data narození
     */
    public function calculate_age_in_months($birth_date) {
        if (!$birth_date) {
            return null;
        }

        $birth = new DateTime($birth_date);
        $now = new DateTime();
        $diff = $birth->diff($now);

        return ($diff->y * 12) + $diff->m;
    }

    /**
     * Vrátí následující pondělí od daného data
     */
    public function get_next_monday($from_date = null) {
        $date = $from_date ? new DateTime($from_date) : new DateTime();

        // Pokud je už pondělí, vrať další pondělí
        if ($date->format('N') == 1) {
            $date->add(new DateInterval('P7D'));
        } else {
            // Najdi nejbližší pondělí
            while ($date->format('N') != 1) {
                $date->add(new DateInterval('P1D'));
            }
        }

        return $date->format('Y-m-d');
    }

    /**
     * Zkontroluje kolize v rozvrhu
     */
    public function check_schedule_conflict($new_course_data, $existing_courses = []) {
        $conflicts = [];

        foreach ($existing_courses as $existing) {
            if ($this->courses_overlap($new_course_data, $existing)) {
                $conflicts[] = $existing;
            }
        }

        return $conflicts;
    }

    private function courses_overlap($course1, $course2) {
        // Stejný den v týdnu
        if ($course1['day_of_week'] != $course2['day_of_week']) {
            return false;
        }

        // Kontrola časového překryvu
        $start1 = strtotime($course1['start_time']);
        $end1 = $start1 + ($course1['lesson_duration'] * 60);

        $start2 = strtotime($course2['start_time']);
        $end2 = $start2 + ($course2['lesson_duration'] * 60);

        return !($end1 <= $start2 || $start1 >= $end2);
    }

    /**
     * Vygeneruje přehledný rozvrh na týden
     */
    public function generate_weekly_schedule($courses) {
        $schedule = [];
        $days = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];

        // Inicializuj prázdný rozvrh
        foreach ($days as $day_num => $day_name) {
            $schedule[$day_num] = [
                'name' => $day_name,
                'courses' => []
            ];
        }

        // Přiřaď kurzy do dnů
        foreach ($courses as $course) {
            $schedule[$course->day_of_week]['courses'][] = [
                'id' => $course->id,
                'title' => $course->title,
                'time' => date('H:i', strtotime($course->start_time)),
                'duration' => $course->lesson_duration,
                'capacity' => $course->max_capacity
            ];
        }

        // Seřaď kurzy v každém dni podle času
        foreach ($schedule as &$day) {
            usort($day['courses'], function($a, $b) {
                return strcmp($a['time'], $b['time']);
            });
        }

        return $schedule;
    }
}
