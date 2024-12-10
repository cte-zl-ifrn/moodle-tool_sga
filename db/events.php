<?php
namespace local_sga\event;

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'local_sga_observer::user_enrolment_created',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'local_sga_observer::user_enrolment_deleted',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'local_sga_observer::user_enrolment_updated',
    ]
];
