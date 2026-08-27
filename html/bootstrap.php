<?php

if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/db.php';
}

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($value)
    {
        return h($value);
    }
}

function ensureColumn($table, $column, $definition)
{
    global $conn;
    if (!$conn) {
        return false;
    }

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && mysqli_num_rows($check) > 0) {
        return true;
    }

    return mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN $definition");
}

function ensureDatabaseSchema()
{
    global $conn;
    if (!$conn) {
        return;
    }

    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'client',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS room_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL UNIQUE,
            max_occupancy TINYINT NOT NULL DEFAULT 2,
            description TEXT NULL,
            rate_per_night DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_type_id INT NOT NULL,
            room_number VARCHAR(20) NOT NULL UNIQUE,
            status ENUM('Available','Occupied','Dirty','Out of Order') NOT NULL DEFAULT 'Available',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS guests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(60) NOT NULL,
            middle_name VARCHAR(60) NULL,
            last_name VARCHAR(60) NOT NULL,
            country VARCHAR(80) NOT NULL,
            contact_number VARCHAR(30) NULL,
            email VARCHAR(120) NULL,
            address TEXT NULL,
            id_type VARCHAR(40) NULL,
            id_number VARCHAR(60) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_number VARCHAR(24) NOT NULL UNIQUE,
            guest_id INT NOT NULL,
            room_id INT NOT NULL,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            occupancy TINYINT NOT NULL DEFAULT 1,
            currency ENUM('NPR','USD') NOT NULL DEFAULT 'NPR',
            price_per_night DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_nights INT NOT NULL DEFAULT 0,
            total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_status ENUM('UNPAID','PAID','PARTIAL') NOT NULL DEFAULT 'UNPAID',
            check_in_status ENUM('NOT CHECKED IN','CHECKED IN') NOT NULL DEFAULT 'NOT CHECKED IN',
            check_out_status ENUM('NOT CHECKED OUT','CHECKED OUT') NOT NULL DEFAULT 'NOT CHECKED OUT',
            source ENUM('admin','client') NOT NULL DEFAULT 'admin',
            user_id INT NULL,
            booked_via VARCHAR(60) NOT NULL DEFAULT 'Walk-in',
            guest_request TEXT NULL,
            room_plan VARCHAR(20) NOT NULL DEFAULT 'EP',
            payment_mode VARCHAR(30) NULL DEFAULT 'Cash',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency ENUM('NPR','USD') NOT NULL DEFAULT 'NPR',
            status ENUM('UNPAID','PAID','PARTIAL') NOT NULL DEFAULT 'UNPAID',
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS extra_charges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            service_name VARCHAR(80) NOT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS complaint_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(24) NOT NULL UNIQUE,
            ticket_title VARCHAR(120) NOT NULL,
            room_number VARCHAR(20) NOT NULL,
            complaint_description TEXT NOT NULL,
            priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
            status ENUM('Open','In Progress','Resolved') NOT NULL DEFAULT 'Open',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS log_sheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            description TEXT NOT NULL,
            written_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS reservation_occupants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            occupant_order INT NOT NULL DEFAULT 2,
            first_name VARCHAR(100) NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            contact_number VARCHAR(50) NULL,
            email VARCHAR(150) NULL,
            id_type VARCHAR(50) NULL,
            id_number VARCHAR(100) NULL,
            address TEXT NULL,
            country VARCHAR(100) NULL,
            price_per_night DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_res_occ (reservation_id),
            CONSTRAINT fk_res_occ_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($queries as $sql) {
        mysqli_query($conn, $sql);
    }

    ensureColumn('users', 'role', "role VARCHAR(20) NOT NULL DEFAULT 'client'");
    ensureColumn('room_types', 'rate_per_night', "rate_per_night DECIMAL(12,2) NOT NULL DEFAULT 0");
    ensureColumn('reservations', 'user_id', "user_id INT NULL");
    ensureColumn('reservations', 'booked_via', "booked_via VARCHAR(60) NOT NULL DEFAULT 'Walk-in'");
    ensureColumn('reservations', 'guest_request', "guest_request TEXT NULL");
    ensureColumn('reservations', 'room_plan', "room_plan VARCHAR(20) NOT NULL DEFAULT 'EP'");
    ensureColumn('reservations', 'payment_mode', "payment_mode VARCHAR(30) NULL DEFAULT 'Cash'");
    ensureColumn('rooms', 'status', "status ENUM('Available','Occupied','Dirty','Out of Order') NOT NULL DEFAULT 'Available'");

    seedDefaultData();
}

function seedDefaultData()
{
    global $conn;
    if (!$conn) {
        return;
    }

    $adminCheck = mysqli_query($conn, "SELECT id FROM users WHERE email = 'admin@hotel.com' LIMIT 1");
    if ($adminCheck && mysqli_num_rows($adminCheck) === 0) {
        $name = 'Hotel Admin';
        $email = 'admin@hotel.com';
        $password = 'admin123';
        $role = 'admin';
        $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $password, $role);
        mysqli_stmt_execute($stmt);
    }

    $staffCheck = mysqli_query($conn, "SELECT id FROM users WHERE email = 'staff@hotel.com' LIMIT 1");
    if ($staffCheck && mysqli_num_rows($staffCheck) === 0) {
        $name = 'Hotel Staff';
        $email = 'staff@hotel.com';
        $password = 'staff123';
        $role = 'staff';
        $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $password, $role);
        mysqli_stmt_execute($stmt);
    }

    $roomTypeCheck = mysqli_query($conn, "SELECT id FROM room_types LIMIT 1");
    if ($roomTypeCheck && mysqli_num_rows($roomTypeCheck) === 0) {
        $roomTypes = [
            ['Heritage Twin', 2, 'Comfortable twin room with heritage decor.', 1800],
            ['Heritage Queen', 2, 'Queen bed room with classic heritage styling.', 2200],
            ['Heritage Family', 3, 'Spacious family room for up to 3 guests.', 3200],
            ['Heritage Deluxe', 2, 'Deluxe heritage room with premium amenities.', 2800],
            ['Durbar Suite', 2, 'Elegant suite inspired by royal durbar halls.', 4200],
            ['Legendary Suite', 2, 'Top-tier suite with luxury finishes.', 6200]
        ];

        $roomsByType = [
            'Heritage Twin' => ['103', '105', '106'],
            'Heritage Queen' => ['104'],
            'Heritage Family' => ['201', '203', '303'],
            'Heritage Deluxe' => ['202', '302'],
            'Durbar Suite' => ['301', '401'],
            'Legendary Suite' => ['402']
        ];

        foreach ($roomTypes as $type) {
            $stmt = mysqli_prepare($conn, "INSERT INTO room_types (name, max_occupancy, description, rate_per_night) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sisd', $type[0], $type[1], $type[2], $type[3]);
            mysqli_stmt_execute($stmt);
            $typeId = mysqli_insert_id($conn);

            foreach ($roomsByType[$type[0]] as $roomNumber) {
                $roomStmt = mysqli_prepare($conn, "INSERT INTO rooms (room_type_id, room_number) VALUES (?, ?)");
                mysqli_stmt_bind_param($roomStmt, 'is', $typeId, $roomNumber);
                mysqli_stmt_execute($roomStmt);
            }
        }
    }

    $rateUpdate = mysqli_query($conn, "SELECT id, name FROM room_types");
    if ($rateUpdate) {
        while ($row = mysqli_fetch_assoc($rateUpdate)) {
            $rate = 0.0;
            $name = $row['name'];
            $rates = [
                'Heritage Twin' => 1800,
                'Heritage Queen' => 2200,
                'Heritage Family' => 3200,
                'Heritage Deluxe' => 2800,
                'Durbar Suite' => 4200,
                'Legendary Suite' => 6200
            ];
            if (isset($rates[$name])) {
                $rate = (float) $rates[$name];
            }
            $stmt = mysqli_prepare($conn, "UPDATE room_types SET rate_per_night = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'di', $rate, $row['id']);
            mysqli_stmt_execute($stmt);
        }
    }
}

ensureDatabaseSchema();
