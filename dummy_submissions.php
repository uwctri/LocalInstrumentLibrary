<?php
// Define dummy submissions for the Local Instrument Library module admin page
$dummy_submissions = [
    [
        "id" => "sub_audit_c",
        "title" => "Substance Use Screening (AUDIT-C)",
        "categories" => [
            "Clinical",
            "Screening"
        ],
        "versions" => [
            [
                "version" => "1.0.0",
                "doc_id" => "sub_audit_100",
                "submitted_by" => "r_carter",
                "submitted_date" => "2026-07-24"
            ]
        ],
        "description" => "The Alcohol Use Disorders Identification Test-Concise (AUDIT-C) is a brief alcohol screening tool that helps to identify patients who are hazardous drinkers or have active alcohol use disorders.",
        "installs" => 0,
        "time" => "2 mins",
        "author_name" => "Dr. Robert Carter",
        "author_email" => "r.carter@geisinger.edu",
        "submitted_by" => "r_carter",
        "submitted_date" => "2026-07-24",
        "submission_date" => "2026-07-24",
        "fields" => [
            ["label" => "How often do you have a drink containing alcohol?", "variable" => "audit_freq", "type" => "radio"],
            ["label" => "How many drinks containing alcohol do you have on a typical day when you are drinking?", "variable" => "audit_qty", "type" => "radio"],
            ["label" => "How often do you have six or more drinks on one occasion?", "variable" => "audit_six_or_more", "type" => "radio"]
        ]
    ],
    [
        "id" => "sub_pediatric_asthma",
        "title" => "Pediatric Asthma Control Assessment",
        "categories" => [
            "Pediatrics",
            "Clinical"
        ],
        "versions" => [
            [
                "version" => "1.0.0",
                "doc_id" => "sub_asthma_100",
                "submitted_by" => "l_reynolds",
                "submitted_date" => "2026-07-25"
            ]
        ],
        "description" => "Standardized assessment tool for monitoring asthma control and symptom severity in pediatric patients over the past 4 weeks.",
        "installs" => 0,
        "time" => "4 mins",
        "author_name" => "Lisa Reynolds, NP",
        "author_email" => "l.reynolds@geisinger.edu",
        "submitted_by" => "l_reynolds",
        "submitted_date" => "2026-07-25",
        "submission_date" => "2026-07-25",
        "fields" => [
            ["label" => "Daytime asthma symptoms more than twice a week?", "variable" => "asthma_day", "type" => "radio"],
            ["label" => "Any night waking due to asthma?", "variable" => "asthma_night", "type" => "radio"],
            ["label" => "Reliever needed for symptoms more than twice a week?", "variable" => "asthma_reliever", "type" => "radio"],
            ["label" => "Any activity limitation due to asthma?", "variable" => "asthma_limit", "type" => "radio"]
        ]
    ]
];
