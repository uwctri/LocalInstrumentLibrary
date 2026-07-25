<?php
// Define mock instruments data for the Local Instrument Library module
$instruments = [
    [
        "id" => "standard_demographics",
        "title" => "Standard Demographics Form",
        "categories" => [
            "Demographics",
            "Clinical"
        ],
        "version" => "v2.1.0",
        "description" => "Captures comprehensive participant demographics including age, gender, race, ethnicity, employment, and education details. Standardized for multi-site clinical trials.",
        "fields_count" => 14,
        "installs" => 342,
        "time" => "5 mins",
        "installed" => true,
        "author_name" => "Sarah Jenkins, PhD",
        "author_email" => "s.jenkins@geisinger.edu",
        "fields" => [
            ["label" => "Participant ID", "variable" => "participant_id", "type" => "text"],
            ["label" => "Date of Birth", "variable" => "dob", "type" => "date"],
            ["label" => "Gender Identity", "variable" => "gender_identity", "type" => "radio"],
            ["label" => "Race (select all that apply)", "variable" => "race", "type" => "checkbox"],
            ["label" => "Ethnicity", "variable" => "ethnicity", "type" => "radio"],
            ["label" => "Primary Language Spoken", "variable" => "primary_lang", "type" => "dropdown"],
            ["label" => "Employment Status", "variable" => "employment_status", "type" => "dropdown"],
            ["label" => "Annual Household Income", "variable" => "household_income", "type" => "radio"],
            ["label" => "Educational Attainment", "variable" => "education_level", "type" => "dropdown"],
            ["label" => "Primary Phone Number", "variable" => "phone_num", "type" => "text"],
            ["label" => "Email Address", "variable" => "email_addr", "type" => "text"],
            ["label" => "Emergency Contact Name", "variable" => "emergency_name", "type" => "text"],
            ["label" => "Emergency Contact Relationship", "variable" => "emergency_rel", "type" => "dropdown"],
            ["label" => "Emergency Contact Phone", "variable" => "emergency_phone", "type" => "text"]
        ]
    ],
    [
        "id" => "phq_9_depression",
        "title" => "PHQ-9 (Patient Health Questionnaire-9)",
        "categories" => [
            "Mental Health",
            "Screening"
        ],
        "version" => "v1.0.2",
        "description" => "Standardized 9-item self-report scale for assessing depression severity. Includes automated scoring logic and branching criteria.",
        "fields_count" => 10,
        "installs" => 512,
        "time" => "3 mins",
        "installed" => false,
        "author_name" => "Marcus Vance, MD",
        "author_email" => "m.vance@geisinger.edu",
        "fields" => [
            ["label" => "Little interest or pleasure in doing things", "variable" => "phq_interest", "type" => "radio"],
            ["label" => "Feeling down, depressed, or hopeless", "variable" => "phq_depressed", "type" => "radio"],
            ["label" => "Trouble falling or staying asleep, or sleeping too much", "variable" => "phq_sleep", "type" => "radio"],
            ["label" => "Feeling tired or having little energy", "variable" => "phq_energy", "type" => "radio"],
            ["label" => "Poor appetite or overeating", "variable" => "phq_appetite", "type" => "radio"],
            ["label" => "Feeling bad about yourself - or that you are a failure", "variable" => "phq_self_failure", "type" => "radio"],
            ["label" => "Trouble concentrating on things, such as reading or watching TV", "variable" => "phq_concentrate", "type" => "radio"],
            ["label" => "Moving or speaking so slowly that other people could have noticed", "variable" => "phq_movement", "type" => "radio"],
            ["label" => "Thoughts that you would be better off dead, or of hurting yourself", "variable" => "phq_suicidal", "type" => "radio"],
            ["label" => "Calculated PHQ-9 Severity Score", "variable" => "phq_total_score", "type" => "calc"]
        ]
    ],
    [
        "id" => "sf_36_health_survey",
        "title" => "SF-36 Health Survey (Short Form)",
        "categories" => [
            "Clinical",
            "Patient Feedback"
        ],
        "version" => "v1.1.0",
        "description" => "A 36-item, patient-reported survey of patient health. Measures health-related quality of life across eight physical and mental domains.",
        "fields_count" => 36,
        "installs" => 189,
        "time" => "12 mins",
        "installed" => false,
        "author_name" => "Global Health Consortium",
        "author_email" => "support@geisinger.edu",
        "fields" => [
            ["label" => "General Health Rating", "variable" => "sf_general_health", "type" => "radio"],
            ["label" => "Limitation: Vigorous activities (running, lifting)", "variable" => "sf_limit_vigorous", "type" => "radio"],
            ["label" => "Limitation: Moderate activities (vacuuming, bowling)", "variable" => "sf_limit_moderate", "type" => "radio"],
            ["label" => "Limitation: Lifting or carrying groceries", "variable" => "sf_limit_groceries", "type" => "radio"],
            ["label" => "Limitation: Climbing several flights of stairs", "variable" => "sf_limit_stairs_mult", "type" => "radio"],
            ["label" => "Limitation: Bending, kneeling, or stooping", "variable" => "sf_limit_bending", "type" => "radio"]
        ]
    ],
    [
        "id" => "covid19_screening",
        "title" => "COVID-19 Intake & Screening",
        "categories" => [
            "Screening",
            "Clinical"
        ],
        "version" => "v3.0.1",
        "description" => "Comprehensive screening questionnaire covering active symptoms, exposure history, vaccination status, and vital sign checks.",
        "fields_count" => 16,
        "installs" => 98,
        "time" => "4 mins",
        "installed" => false,
        "author_name" => "REDCap Screening Group",
        "author_email" => "screening-team@geisinger.edu",
        "fields" => [
            ["label" => "Fever or chills in last 14 days", "variable" => "covid_fever", "type" => "radio"],
            ["label" => "Difficulty breathing or shortness of breath", "variable" => "covid_breathing", "type" => "radio"],
            ["label" => "New or worsening cough", "variable" => "covid_cough", "type" => "radio"],
            ["label" => "New loss of taste or smell", "variable" => "covid_taste_smell", "type" => "radio"],
            ["label" => "Close contact with confirmed COVID-19 case", "variable" => "covid_exposure", "type" => "radio"],
            ["label" => "Vaccination Status", "variable" => "covid_vaccine_status", "type" => "dropdown"]
        ]
    ],
    [
        "id" => "vital_signs_log",
        "title" => "Daily Vital Signs Log",
        "categories" => [
            "Clinical"
        ],
        "version" => "v1.2.0",
        "description" => "Log sheet for recording standard physical metrics including blood pressure, heart rate, temperature, weight, and blood glucose level.",
        "fields_count" => 8,
        "installs" => 275,
        "time" => "2 mins",
        "installed" => false,
        "author_name" => "Clinical Research Core",
        "author_email" => "clinical-core@geisinger.edu",
        "fields" => [
            ["label" => "Measurement Date & Time", "variable" => "vitals_timestamp", "type" => "datetime"],
            ["label" => "Weight (kg)", "variable" => "vitals_weight", "type" => "text"],
            ["label" => "Height (cm)", "variable" => "vitals_height", "type" => "text"],
            ["label" => "Systolic Blood Pressure", "variable" => "vitals_sys_bp", "type" => "text"],
            ["label" => "Diastolic Blood Pressure", "variable" => "vitals_dia_bp", "type" => "text"],
            ["label" => "Heart Rate (bpm)", "variable" => "vitals_heart_rate", "type" => "text"],
            ["label" => "Body Temperature (°C)", "variable" => "vitals_temp", "type" => "text"]
        ]
    ],
    [
        "id" => "pediatric_asthma_control",
        "title" => "Pediatric Asthma Control Test (ACT)",
        "categories" => [
            "Pediatrics",
            "Screening"
        ],
        "version" => "v1.0.1",
        "description" => "Standardized questionnaire for assessing asthma control in children aged 4 to 11 years. Includes parent and child response sections.",
        "fields_count" => 7,
        "installs" => 145,
        "time" => "3 mins",
        "installed" => false,
        "author_name" => "Marcus Vance, MD",
        "author_email" => "m.vance@geisinger.edu",
        "fields" => [
            ["label" => "How is your asthma today?", "variable" => "act_child_status", "type" => "radio"],
            ["label" => "How active can you be?", "variable" => "act_child_activity", "type" => "radio"],
            ["label" => "Do you cough because of asthma?", "variable" => "act_child_cough", "type" => "radio"],
            ["label" => "Do you wake up at night?", "variable" => "act_child_wake", "type" => "radio"],
            ["label" => "Parent: How many days of symptoms?", "variable" => "act_parent_days", "type" => "radio"],
            ["label" => "Parent: How many inhaler puffs?", "variable" => "act_parent_inhaler", "type" => "radio"],
            ["label" => "Total Asthma Control Score", "variable" => "act_total_score", "type" => "calc"]
        ]
    ],
    [
        "id" => "mmse_cognitive",
        "title" => "Mini-Mental State Examination (MMSE)",
        "categories" => [
            "Neurology",
            "Mental Health"
        ],
        "version" => "v2.0.0",
        "description" => "A 30-point questionnaire used extensively in clinical and research settings to measure cognitive impairment and screen for dementia.",
        "fields_count" => 11,
        "installs" => 302,
        "time" => "8 mins",
        "installed" => false,
        "author_name" => "Sarah Jenkins, PhD",
        "author_email" => "s.jenkins@geisinger.edu",
        "fields" => [
            ["label" => "What is the year, season, date, day, month?", "variable" => "mmse_time", "type" => "radio"],
            ["label" => "Where are we: state, county, town, hospital, floor?", "variable" => "mmse_place", "type" => "radio"],
            ["label" => "Name three objects: 1 second to say each", "variable" => "mmse_registration", "type" => "radio"],
            ["label" => "Serial 7s backward from 100", "variable" => "mmse_attention", "type" => "radio"],
            ["label" => "Ask for the 3 objects repeated above", "variable" => "mmse_recall", "type" => "radio"],
            ["label" => "Show pencil and watch: name them", "variable" => "mmse_naming", "type" => "radio"]
        ]
    ],
    [
        "id" => "karnofsky_performance",
        "title" => "Karnofsky Performance Status Scale",
        "categories" => [
            "Oncology",
            "Clinical"
        ],
        "version" => "v1.0.0",
        "description" => "Standardized scale to assess a patient's functional impairment, used to compare effectiveness of different therapies and assess prognosis.",
        "fields_count" => 3,
        "installs" => 112,
        "time" => "1 min",
        "installed" => false,
        "author_name" => "Clinical Research Core",
        "author_email" => "clinical-core@geisinger.edu",
        "fields" => [
            ["label" => "Patient functional status rating", "variable" => "karnofsky_rating", "type" => "dropdown"],
            ["label" => "Level of care required", "variable" => "karnofsky_care", "type" => "radio"],
            ["label" => "Able to work status", "variable" => "karnofsky_work", "type" => "radio"]
        ]
    ],
    [
        "id" => "heart_failure_yale",
        "title" => "Minnesota Living with Heart Failure",
        "categories" => [
            "Cardiology",
            "Patient Feedback"
        ],
        "version" => "v1.1.2",
        "description" => "A self-administered patient-reported outcome measure designed to assess the effects of heart failure on a patient's daily life.",
        "fields_count" => 6,
        "installs" => 84,
        "time" => "6 mins",
        "installed" => false,
        "author_name" => "Marcus Vance, MD",
        "author_email" => "m.vance@geisinger.edu",
        "fields" => [
            ["label" => "Shortness of breath in daily life", "variable" => "mlhfq_breath", "type" => "radio"],
            ["label" => "Swelling in ankles or legs", "variable" => "mlhfq_swelling", "type" => "radio"],
            ["label" => "Feeling tired or fatigued", "variable" => "mlhfq_fatigue", "type" => "radio"],
            ["label" => "Difficulty working around house", "variable" => "mlhfq_work", "type" => "radio"],
            ["label" => "Difficulty walking or climbing stairs", "variable" => "mlhfq_stairs", "type" => "radio"],
            ["label" => "Sleep affected by heart condition", "variable" => "mlhfq_sleep", "type" => "radio"]
        ]
    ],
    [
        "id" => "satisfaction_survey",
        "title" => "Patient Experience & Satisfaction",
        "categories" => [
            "Patient Feedback"
        ],
        "version" => "v2.0.1",
        "description" => "Feedback questionnaire measuring patient satisfaction with clinical staff, facilities, scheduling efficiency, and overall care quality.",
        "fields_count" => 5,
        "installs" => 421,
        "time" => "4 mins",
        "installed" => false,
        "author_name" => "REDCap Screening Group",
        "author_email" => "screening-team@geisinger.edu",
        "fields" => [
            ["label" => "Quality of communication with doctor", "variable" => "sat_doctor_comm", "type" => "radio"],
            ["label" => "Cleanliness of clinical facilities", "variable" => "sat_facilities", "type" => "radio"],
            ["label" => "Ease of scheduling appointment", "variable" => "sat_scheduling", "type" => "radio"],
            ["label" => "Friendliness of reception staff", "variable" => "sat_reception", "type" => "radio"],
            ["label" => "Overall visit rating", "variable" => "sat_overall", "type" => "radio"]
        ]
    ],
    [
        "id" => "comprehensive_clinical_assessment",
        "title" => "Comprehensive Clinical Assessment (CCA)",
        "categories" => [
            "Clinical",
            "Screening"
        ],
        "version" => "v1.0.0",
        "description" => "Extensive multi-system clinical assessment questionnaire. Includes detailed sections on cardiovascular health, respiratory function, neurological baseline, psychiatric screening, and lifestyle risk factors.",
        "fields_count" => 32,
        "installs" => 45,
        "time" => "15 mins",
        "installed" => false,
        "author_name" => "Clinical Research Core",
        "author_email" => "clinical-core@geisinger.edu",
        "fields" => [
            ["label" => "Patient Height (cm)", "variable" => "cca_height", "type" => "text"],
            ["label" => "Patient Weight (kg)", "variable" => "cca_weight", "type" => "text"],
            ["label" => "Body Mass Index (BMI)", "variable" => "cca_bmi", "type" => "calc"],
            ["label" => "Systolic Blood Pressure (mmHg)", "variable" => "cca_systolic", "type" => "text"],
            ["label" => "Diastolic Blood Pressure (mmHg)", "variable" => "cca_diastolic", "type" => "text"],
            ["label" => "Resting Heart Rate (bpm)", "variable" => "cca_heart_rate", "type" => "text"],
            ["label" => "Body Temperature (°C)", "variable" => "cca_temp", "type" => "text"],
            ["label" => "Oxygen Saturation (SpO2)", "variable" => "cca_spo2", "type" => "text"],
            ["label" => "History of Hypertension", "variable" => "cca_hx_hypertension", "type" => "radio"],
            ["label" => "History of Diabetes Mellitus", "variable" => "cca_hx_diabetes", "type" => "radio"],
            ["label" => "History of Hyperlipidemia", "variable" => "cca_hx_hyperlipidemia", "type" => "radio"],
            ["label" => "History of Coronary Heart Disease", "variable" => "cca_hx_chd", "type" => "radio"],
            ["label" => "Active Smoker Status", "variable" => "cca_smoker", "type" => "dropdown"],
            ["label" => "Alcohol Consumption Frequency", "variable" => "cca_alcohol", "type" => "dropdown"],
            ["label" => "Average Sleep Duration (hours)", "variable" => "cca_sleep", "type" => "text"],
            ["label" => "Physical Activity Level", "variable" => "cca_activity", "type" => "dropdown"],
            ["label" => "Current Chest Pain Symptoms", "variable" => "cca_chest_pain", "type" => "radio"],
            ["label" => "Shortness of Breath Status", "variable" => "cca_dyspnea", "type" => "radio"],
            ["label" => "Chronic Cough Presence", "variable" => "cca_cough", "type" => "radio"],
            ["label" => "Wheezing or Stridor Status", "variable" => "cca_wheeze", "type" => "radio"],
            ["label" => "Frequent Headache Occurrences", "variable" => "cca_headache", "type" => "radio"],
            ["label" => "History of Seizures or Tremors", "variable" => "cca_seizures", "type" => "radio"],
            ["label" => "Dizziness or Vertigo baseline", "variable" => "cca_dizziness", "type" => "radio"],
            ["label" => "History of Depression or Anxiety", "variable" => "cca_mental_hx", "type" => "radio"],
            ["label" => "Current Stress Level Rating", "variable" => "cca_stress", "type" => "dropdown"],
            ["label" => "Family History: Heart Attack", "variable" => "cca_fhx_mi", "type" => "radio"],
            ["label" => "Family History: Stroke", "variable" => "cca_fhx_stroke", "type" => "radio"],
            ["label" => "Family History: Cancer", "variable" => "cca_fhx_cancer", "type" => "radio"],
            ["label" => "Current Prescribed Medications", "variable" => "cca_meds", "type" => "text"],
            ["label" => "Drug Allergy Status", "variable" => "cca_allergy_drug", "type" => "radio"],
            ["label" => "Food Allergy Status", "variable" => "cca_allergy_food", "type" => "radio"],
            ["label" => "General Self-Reported Health Status", "variable" => "cca_general_health", "type" => "dropdown"]
        ]
    ]
];
