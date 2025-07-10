<?php

namespace tool_sga\event;

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'tool_sga_observer::user_enrolment_created',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'tool_sga_observer::user_enrolment_deleted',
    ],
    [
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'tool_sga_observer::user_enrolment_updated',
    ]
];
