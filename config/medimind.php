<?php

/*
| MediMind tunables. Thresholds for reminders and risk scoring live here
| so they can be changed without touching code.
*/
return [
    'disclaimer' => 'MediMind supports, but does not replace, the advice of your doctor or pharmacist.',

    // How many days of upcoming doses are kept ready (today included).
    'dose_generate_days' => 7,

    'dose' => [
        // A pending dose becomes "missed" this many minutes after its scheduled time.
        'grace_minutes' => 60,
        // A dose can be confirmed up to this many minutes before its scheduled time.
        'early_minutes' => 60,
        // "Remind me later" delay, and how many times a dose can be snoozed.
        'snooze_minutes' => 10,
        'max_snoozes' => 3,
    ],

    /*
    | Adherence-risk score (0-100). A transparent, rule-based estimate: each factor
    | adds points up to its weight, and the weights add up to 100. The score only
    | flags who may need follow-up. It is not a diagnosis.
    */
    'risk' => [
        // Fewer decided doses (taken or missed) in the last 14 days than this: too little data to score.
        'min_doses' => 5,
        // Score at or above these values is medium / high risk.
        'medium' => 30,
        'high' => 60,
        'weights' => [
            'adherence' => 40,      // share of doses missed, last 14 days
            'streak' => 25,         // doses missed in a row, most recent
            'time_pattern' => 10,   // one time of day is often missed
            'weekday_pattern' => 5, // one weekday is often missed
            'complexity' => 10,     // many doses per day
            'snooze' => 10,         // doses often snoozed before being taken
        ],
        // A time of day or weekday counts as a pattern at this miss rate, with at least this many doses.
        'pattern_miss_rate' => 0.5,
        'slot_min_doses' => 3,
        'weekday_min_doses' => 2,
        // Doses per day at which the complexity factor is at its maximum.
        'complexity_full_at' => 8,
    ],

    'emergency' => [
        // This many missed doses in a row alerts the trusted contacts and the doctor.
        'consecutive_misses' => 3,
        'max_contacts' => 5,
    ],

    'refill' => [
        // A refill shows up on the pharmacy's list this many days before the supply runs out.
        'due_days' => 7,
        // Overdue by more than this many days: "may have stopped refilling".
        'stopped_after_days' => 14,
        // A pharmacy cannot message the same patient about the same medicine more often than this.
        'followup_cooldown_hours' => 24,
    ],

    // How much earlier a reminder moves when the patient accepts a suggestion.
    'reminder_shift_minutes' => 30,
];
