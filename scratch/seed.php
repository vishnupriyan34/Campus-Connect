<?php
// Database Seeding Script - Runnable via CLI or Web

require_once __DIR__ . '/../config/db.php';

// If run from browser, format as HTML
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>Database Seeder</title>";
    echo "<style>body{font-family:sans-serif;background:#0b0f19;color:#f8fafc;padding:2rem;}pre{background:#131b2e;padding:1rem;border-radius:8px;border:1px solid rgba(255,255,255,0.08);}</style>";
    echo "</head><body><h1>Campus Connect Database Seeder</h1><pre>";
}

echo "Starting database seeding...\n";

try {
    // Disable foreign key checks to truncate tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $tables = [
        'audit_logs',
        'verification_requests',
        'notifications',
        'offer_letters',
        'applications',
        'drives',
        'companies',
        'students',
        'users'
    ];
    
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table` ");
        echo "Truncated table: $table\n";
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // 1. Seed Users
    $users = [
        [
            'name' => 'Admin Controller',
            'email' => 'admin@campusconnect.com',
            'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'admin'
        ],
        [
            'name' => 'TPO Office',
            'email' => 'tpo@campusconnect.com',
            'password_hash' => password_hash('tpo123', PASSWORD_BCRYPT),
            'role' => 'staff'
        ],
        [
            'name' => 'Siva Prasath',
            'email' => 'siva@campusconnect.com',
            'password_hash' => password_hash('student123', PASSWORD_BCRYPT),
            'role' => 'student'
        ],
        [
            'name' => 'Rohan Kumar',
            'email' => 'rohan@campusconnect.com',
            'password_hash' => password_hash('student123', PASSWORD_BCRYPT),
            'role' => 'student'
        ],
        [
            'name' => 'Pooja Sharma',
            'email' => 'pooja@campusconnect.com',
            'password_hash' => password_hash('student123', PASSWORD_BCRYPT),
            'role' => 'student'
        ]
    ];
    
    $stmtUser = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, role) 
        VALUES (:name, :email, :password_hash, :role)
    ");
    
    $userIds = [];
    foreach ($users as $user) {
        $stmtUser->execute([
            ':name' => $user['name'],
            ':email' => $user['email'],
            ':password_hash' => $user['password_hash'],
            ':role' => $user['role']
        ]);
        $userIds[$user['email']] = $pdo->lastInsertId();
    }
    echo "Seeded users successfully.\n";
    
    // 2. Seed Students Details
    $students = [
        [
            'user_id' => $userIds['siva@campusconnect.com'],
            'department' => 'CSE',
            'cgpa' => 8.50,
            'backlog_count' => 0,
            'resume_path' => null,
            'skills' => 'PHP, JavaScript, SQL, HTML, CSS, React, Git'
        ],
        [
            'user_id' => $userIds['rohan@campusconnect.com'],
            'department' => 'ECE',
            'cgpa' => 7.20,
            'backlog_count' => 1,
            'resume_path' => null,
            'skills' => 'C++, Python, Embedded Systems, IoT, Git'
        ],
        [
            'user_id' => $userIds['pooja@campusconnect.com'],
            'department' => 'ME',
            'cgpa' => 6.80,
            'backlog_count' => 0,
            'resume_path' => null,
            'skills' => 'SolidWorks, AutoCAD, Python, Mechanical Design'
        ]
    ];
    
    $stmtStudent = $pdo->prepare("
        INSERT INTO students (user_id, department, cgpa, backlog_count, resume_path, skills) 
        VALUES (:user_id, :department, :cgpa, :backlog_count, :resume_path, :skills)
    ");
    
    foreach ($students as $student) {
        $stmtStudent->execute([
            ':user_id' => $student['user_id'],
            ':department' => $student['department'],
            ':cgpa' => $student['cgpa'],
            ':backlog_count' => $student['backlog_count'],
            ':resume_path' => $student['resume_path'],
            ':skills' => $student['skills']
        ]);
    }
    echo "Seeded students successfully.\n";
    
    // 3. Seed Companies
    $companies = [
        [
            'name' => 'Google LLC',
            'description' => "Role: Software Engineering Intern\nRequirements:\n- Proficient in C++, Java, or Python\n- Good understanding of web technologies: HTML, CSS, JavaScript\n- Knowledge of SQL databases\n- Excellent problem-solving skills",
            'package_range' => '15 - 35 LPA',
            'contact_name' => 'Sundar P',
            'contact_email' => 'sundar@google.com'
        ],
        [
            'name' => 'Microsoft Corporation',
            'description' => "Role: Associate Consultant\nRequirements:\n- Strong networking fundamentals\n- Familiarity with cloud architectures (Azure, AWS)\n- Basic script writing in Python or PowerShell\n- Good communication skills",
            'package_range' => '12 - 25 LPA',
            'contact_name' => 'Satya N',
            'contact_email' => 'satya@microsoft.com'
        ],
        [
            'name' => 'TCS (Tata Consultancy Services)',
            'description' => "Role: System Engineer\nRequirements:\n- Basic programming knowledge in C, C++, Java, or Python\n- Logical reasoning and quantitative aptitude\n- Good communication skills",
            'package_range' => '3.6 - 7.5 LPA',
            'contact_name' => 'Chandra K',
            'contact_email' => 'recruiter@tcs.com'
        ]
    ];
    
    $stmtCompany = $pdo->prepare("
        INSERT INTO companies (name, description, package_range, contact_name, contact_email) 
        VALUES (:name, :description, :package_range, :contact_name, :contact_email)
    ");
    
    $companyIds = [];
    foreach ($companies as $company) {
        $stmtCompany->execute([
            ':name' => $company['name'],
            ':description' => $company['description'],
            ':package_range' => $company['package_range'],
            ':contact_name' => $company['contact_name'],
            ':contact_email' => $company['contact_email']
        ]);
        $companyIds[$company['name']] = $pdo->lastInsertId();
    }
    echo "Seeded companies successfully.\n";
    
    // 4. Seed Drives
    $drives = [
        [
            'company_id' => $companyIds['Google LLC'],
            'title' => 'Software Engineer Intern (Winter 2026)',
            'job_description' => "Role: Software Engineering Intern\nRequirements:\n- Proficient in C++, Java, or Python\n- Good understanding of web technologies: HTML, CSS, JavaScript\n- Knowledge of SQL databases\n- Excellent problem-solving skills",
            'eligibility_cgpa' => 8.00,
            'eligibility_branch' => 'CSE, IT',
            'eligibility_max_backlogs' => 0,
            'test_date' => '2026-08-15 10:00:00',
            'interview_date' => '2026-08-20 09:00:00',
            'status' => 'open'
        ],
        [
            'company_id' => $companyIds['Microsoft Corporation'],
            'title' => 'Associate Consultant & Support Engineer',
            'job_description' => "Role: Associate Consultant\nRequirements:\n- Strong networking fundamentals\n- Familiarity with cloud architectures (Azure, AWS)\n- Basic script writing in Python or PowerShell\n- Good communication skills",
            'eligibility_cgpa' => 7.00,
            'eligibility_branch' => 'CSE, ECE, IT',
            'eligibility_max_backlogs' => 1,
            'test_date' => '2026-08-16 14:00:00',
            'interview_date' => '2026-08-21 10:00:00',
            'status' => 'open'
        ],
        [
            'company_id' => $companyIds['TCS (Tata Consultancy Services)'],
            'title' => 'System Engineer - Ninja & Digital',
            'job_description' => "Role: System Engineer\nRequirements:\n- Basic programming knowledge in C, C++, Java, or Python\n- Logical reasoning and quantitative aptitude\n- Good communication skills",
            'eligibility_cgpa' => 6.00,
            'eligibility_branch' => 'All',
            'eligibility_max_backlogs' => 2,
            'test_date' => '2026-08-10 09:00:00',
            'interview_date' => '2026-08-14 11:00:00',
            'status' => 'open'
        ]
    ];
    
    $stmtDrive = $pdo->prepare("
        INSERT INTO drives (company_id, title, job_description, eligibility_cgpa, eligibility_branch, eligibility_max_backlogs, test_date, interview_date, status) 
        VALUES (:company_id, :title, :job_description, :eligibility_cgpa, :eligibility_branch, :eligibility_max_backlogs, :test_date, :interview_date, :status)
    ");
    
    foreach ($drives as $drive) {
        $stmtDrive->execute([
            ':company_id' => $drive['company_id'],
            ':title' => $drive['title'],
            ':job_description' => $drive['job_description'],
            ':eligibility_cgpa' => $drive['eligibility_cgpa'],
            ':eligibility_branch' => $drive['eligibility_branch'],
            ':eligibility_max_backlogs' => $drive['eligibility_max_backlogs'],
            ':test_date' => $drive['test_date'],
            ':interview_date' => $drive['interview_date'],
            ':status' => $drive['status']
        ]);
    }
    echo "Seeded drives successfully.\n";
    
    // 5. Seed System Audit logs
    $pdo->exec("
        INSERT INTO audit_logs (actor_id, action, target) 
        VALUES (2, 'Drive Created', 'Google LLC: Software Engineer Intern (Winter 2026)')
    ");
    
    echo "Database seeding finished successfully!\n";
    
} catch (PDOException $e) {
    echo "Database seeding failed: " . $e->getMessage() . "\n";
}

if (!$is_cli) {
    echo "</pre><a href='../login.php' style='color:#6366f1;text-decoration:none;font-weight:bold;'>&larr; Go to Login</a></body></html>";
}
?>
