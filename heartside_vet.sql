-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 12, 2026 at 10:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `heartside_vet`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `AppointmentID` int(11) NOT NULL,
  `PetID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `ServiceID` int(11) DEFAULT NULL,
  `AppointmentDate` date NOT NULL,
  `AppointmentTime` time NOT NULL,
  `Reason` varchar(255) DEFAULT NULL COMMENT 'Service name snapshot at booking time',
  `Notes` text DEFAULT NULL,
  `Status` enum('Scheduled','Completed','Cancelled','No-Show') NOT NULL DEFAULT 'Scheduled',
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `UpdateReason` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`AppointmentID`, `PetID`, `ClientID`, `ServiceID`, `AppointmentDate`, `AppointmentTime`, `Reason`, `Notes`, `Status`, `IsDeleted`, `CreatedAt`, `UpdatedAt`, `UpdateReason`) VALUES
(1, 1, 1, 1, '2026-06-08', '09:30:00', 'Annual Wellness Exam', 'First visit this year.', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(2, 3, 2, 2, '2026-06-08', '10:00:00', 'Vaccination – Anti-Rabies', 'Booster shot due.', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-11 10:17:01', NULL),
(3, 4, 3, 4, '2026-06-08', '10:30:00', 'Vaccination – FVRCP (Cat)', 'First dose.', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(4, 5, 3, 5, '2026-06-08', '11:00:00', 'Deworming', 'Routine deworming.', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(5, 6, 4, 9, '2026-06-08', '13:00:00', 'Dental Cleaning (Scaling)', 'Owner noticed tartar build-up.', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-08 22:23:16', NULL),
(6, 7, 5, 3, '2026-06-08', '13:30:00', 'Vaccination – 5-in-1 (DHPP)', 'Annual booster.', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-11 10:42:22', NULL),
(7, 8, 6, 11, '2026-06-08', '14:00:00', 'X-Ray – Single View', 'Limping on right hind leg.', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(8, 11, 9, 1, '2026-06-08', '14:30:00', 'Annual Wellness Exam', '', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(9, 12, 10, 6, '2026-06-08', '15:00:00', 'Flea & Tick Treatment', 'Heavy infestation reported.', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(10, 1, 1, 14, '2026-06-01', '09:30:00', 'Grooming – Bath & Blow Dry', 'Requested hypoallergenic shampoo.', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(11, 3, 2, 1, '2026-05-25', '10:00:00', 'Annual Wellness Exam', '', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(12, 6, 4, 12, '2026-06-05', '11:00:00', 'Blood Chemistry Panel', 'Pre-surgery labs.', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(13, 9, 7, 15, '2026-06-03', '13:30:00', 'Grooming – Full Trim', '', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(14, 4, 3, 2, '2026-06-06', '09:00:00', 'Vaccination – Anti-Rabies', '', 'Cancelled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(15, 15, 9, 13, '2026-06-10', '10:00:00', 'Urinalysis', 'Frequent urination reported.', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(16, 14, 6, 7, '2026-06-11', '09:30:00', 'Spay (Female)', 'Pre-op clearance done.', 'Completed', 0, '2026-06-08 18:35:42', '2026-06-11 04:33:09', NULL),
(17, 2, 1, 16, '2026-06-09', '10:30:00', 'Nail Trim', '', 'Scheduled', 0, '2026-06-08 18:35:42', '2026-06-11 03:52:00', 'Client will continue with the appointment'),
(18, 12, 10, 12, '2026-06-15', '11:00:00', 'Blood Chemistry Panel', '', 'Completed', 0, '2026-06-10 11:26:17', '2026-06-11 10:08:35', NULL),
(19, 14, 6, 20, '2026-06-15', '11:30:00', 'Follow-up consultation', NULL, 'Scheduled', 1, '2026-06-11 04:34:51', '2026-06-11 10:06:58', NULL),
(20, 12, 10, 1, '2026-06-11', '11:30:00', 'Follow-up consultation', '', 'Scheduled', 0, '2026-06-11 10:08:35', '2026-06-11 19:57:38', 'Client requested to change the appointment time'),
(21, 9, 7, 5, '2026-06-26', '15:30:00', 'Follow-up consultation', NULL, 'Scheduled', 1, '2026-06-11 19:18:49', '2026-06-11 19:19:28', NULL),
(22, 12, 10, NULL, '2026-06-18', '15:30:00', 'Follow-up consultation', NULL, 'Scheduled', 0, '2026-06-12 11:34:36', '2026-06-12 11:34:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `BillingID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `PetID` int(11) DEFAULT NULL,
  `ConsultationID` int(11) DEFAULT NULL,
  `BillingDate` date NOT NULL,
  `TotalAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `AmountPaid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `PaymentMethod` varchar(60) NOT NULL DEFAULT 'Cash',
  `PaymentStatus` enum('Pending','Partial','Paid') NOT NULL DEFAULT 'Pending',
  `Notes` text DEFAULT NULL,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `UpdateReason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`BillingID`, `ClientID`, `PetID`, `ConsultationID`, `BillingDate`, `TotalAmount`, `Discount`, `AmountPaid`, `PaymentMethod`, `PaymentStatus`, `Notes`, `IsDeleted`, `CreatedAt`, `UpdatedAt`, `UpdateReason`) VALUES
(1, 1, 1, 1, '2026-06-01', 350.00, 0.00, 350.00, 'Cash', 'Paid', 'Auto-generated from Consultation #1', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(2, 2, 3, 2, '2026-05-25', 500.00, 0.00, 500.00, 'GCash', 'Paid', 'Auto-generated from Consultation #2', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(3, 4, 6, 3, '2026-06-05', 1200.00, 0.00, 600.00, 'Cash', 'Partial', 'Auto-generated from Consultation #3', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(4, 7, 9, 4, '2026-06-03', 750.00, 0.00, 750.00, 'Maya', 'Paid', 'Auto-generated from Consultation #4', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(5, 3, 5, 5, '2026-05-29', 1300.00, 0.00, 0.00, 'Cash', 'Pending', 'Auto-generated from Consultation #5', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(6, 6, 8, 6, '2026-05-19', 1300.00, 0.00, 1300.00, 'Cash', 'Paid', 'Auto-generated from Consultation #6', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(7, 5, 7, NULL, '2026-06-07', 350.00, 0.00, 350.00, 'GCash', 'Paid', 'Vaccination invoice', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(8, 9, 11, NULL, '2026-06-06', 500.00, 50.00, 500.00, 'Cash', 'Paid', 'Senior pet discount applied', 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42', NULL),
(9, 10, 12, NULL, '2026-06-04', 500.00, 0.00, 500.00, 'Cash', 'Paid', 'Flea treatment — pending collection', 0, '2026-06-08 18:35:42', '2026-06-08 23:18:19', 'Fully paid'),
(10, 4, 6, 7, '2026-06-08', 1500.00, 0.00, 0.00, 'Cash', 'Pending', 'Auto-generated from Consultation #7', 0, '2026-06-08 22:23:16', '2026-06-08 22:23:16', NULL),
(11, 7, 9, 4, '2026-06-03', 750.00, 0.00, 750.00, 'Cash', 'Paid', '', 0, '2026-06-08 23:14:24', '2026-06-08 23:14:24', NULL),
(12, 2, 3, 2, '2026-05-25', 500.00, 0.00, 500.00, 'Cash', 'Paid', '', 0, '2026-06-10 11:30:57', '2026-06-10 11:30:57', NULL),
(13, 6, 14, 8, '2026-06-10', 3500.00, 0.00, 0.00, 'Cash', 'Pending', 'Auto-generated from Consultation #8', 0, '2026-06-11 04:33:09', '2026-06-11 04:33:09', NULL),
(14, 6, 14, 8, '2026-06-11', 3500.00, 0.00, 3500.00, 'Cash', 'Paid', '', 0, '2026-06-11 04:36:08', '2026-06-11 04:36:08', NULL),
(15, 10, 12, 9, '2026-06-11', 1200.00, 0.00, 1200.00, 'Cash', 'Paid', 'Auto-generated from Consultation #9', 0, '2026-06-11 10:08:35', '2026-06-11 10:10:09', 'fully paid'),
(16, 2, 3, 10, '2026-06-11', 350.00, 0.00, 0.00, 'Cash', 'Pending', 'Auto-generated from Consultation #10', 0, '2026-06-11 10:17:01', '2026-06-11 10:17:01', NULL),
(17, 5, 7, 11, '2026-06-08', 450.00, 0.00, 450.00, 'Cash', 'Paid', '', 0, '2026-06-11 10:44:30', '2026-06-11 10:44:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `BillingItemID` int(11) NOT NULL,
  `BillingID` int(11) NOT NULL,
  `ServiceID` int(11) DEFAULT NULL,
  `Description` varchar(255) NOT NULL,
  `Quantity` int(11) NOT NULL DEFAULT 1,
  `UnitPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_items`
--

INSERT INTO `billing_items` (`BillingItemID`, `BillingID`, `ServiceID`, `Description`, `Quantity`, `UnitPrice`, `Subtotal`, `CreatedAt`) VALUES
(1, 1, 14, 'Grooming – Bath & Blow Dry', 1, 350.00, 350.00, '2026-06-08 18:35:42'),
(2, 2, 1, 'Annual Wellness Exam', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(3, 3, 12, 'Blood Chemistry Panel', 1, 1200.00, 1200.00, '2026-06-08 18:35:42'),
(4, 4, 15, 'Grooming – Full Trim', 1, 600.00, 600.00, '2026-06-08 18:35:42'),
(5, 4, 16, 'Nail Trim', 1, 150.00, 150.00, '2026-06-08 18:35:42'),
(6, 5, 19, 'IV Fluid Therapy', 1, 800.00, 800.00, '2026-06-08 18:35:42'),
(7, 5, 1, 'Annual Wellness Exam', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(8, 6, 11, 'X-Ray – Single View', 1, 800.00, 800.00, '2026-06-08 18:35:42'),
(9, 6, 1, 'Annual Wellness Exam', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(10, 7, 3, 'Vaccination – 5-in-1 (DHPP)', 1, 350.00, 350.00, '2026-06-08 18:35:42'),
(11, 8, 1, 'Annual Wellness Exam', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(12, 9, 6, 'Flea & Tick Treatment', 1, 300.00, 300.00, '2026-06-08 18:35:42'),
(13, 11, 15, 'Grooming – Full Trim', 1, 600.00, 600.00, '2026-06-08 23:14:24'),
(14, 11, 16, 'Nail Trim', 1, 150.00, 150.00, '2026-06-08 23:14:24'),
(15, 12, 1, 'Annual Wellness Exam', 1, 500.00, 500.00, '2026-06-10 11:30:57'),
(16, 14, 7, 'Spay (Female)', 1, 3500.00, 3500.00, '2026-06-11 04:36:08'),
(17, 17, 3, 'Vaccination – 5-in-1 (DHPP)', 1, 450.00, 450.00, '2026-06-11 10:44:30');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `ClientID` int(11) NOT NULL,
  `FirstName` varchar(100) NOT NULL,
  `LastName` varchar(100) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `Phone` int(11) NOT NULL,
  `Address` text NOT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`ClientID`, `FirstName`, `LastName`, `Email`, `Phone`, `Address`, `IsActive`, `IsDeleted`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'Maria', 'Santos', 'maria.santos@gmail.com', 2147483647, '123 Sampaguita St., Quezon City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(2, 'Jose', 'Reyes', 'jose.reyes@yahoo.com', 2147483647, '45 Rizal Ave., Caloocan City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(3, 'Ana', 'Dela Cruz', 'anadelacruz@gmail.com', 2147483647, '88 Mabini St., Pasig City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(4, 'Carlo', 'Ramos', 'carlo.ramos@outlook.com', 2147483647, '20 Magsaysay Blvd., Manila', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(5, 'Lourdes', 'Bautista', 'lbautista@gmail.com', 2147483647, '7 Orchid St., Marikina City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(6, 'Roberto', 'Aquino', 'r.aquino@promail.ph', 2147483647, '55 Bonifacio St., Mandaluyong', 1, 0, '2026-04-01 18:35:42', '2026-06-11 04:06:23'),
(7, 'Jenny', 'Torres', 'jenny.torres@gmail.com', 2147483647, 'Unit 4B Sunrise Condo, Pasay City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(8, 'Mark', 'Garcia', 'markgarcia@gmail.com', 2147483647, '101 Luna St., Las Piñas City', 0, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(9, 'Patricia', 'Flores', 'pat.flores@yahoo.com', 2147483647, '30 Dahlia St., Muntinlupa City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(10, 'Miguel', 'Mendoza', 'miguelmendoza@gmail.com', 2147483647, '12 Acacia Ave., Taguig City', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `ConsultationID` int(11) NOT NULL,
  `PetID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `AppointmentID` int(11) DEFAULT NULL COMMENT 'Linked appointment (optional)',
  `ConsultationDate` date NOT NULL,
  `VetName` varchar(150) NOT NULL,
  `ChiefComplaint` text DEFAULT NULL,
  `Diagnosis` text DEFAULT NULL,
  `Treatment` text DEFAULT NULL,
  `Prescription` text DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `UpdateReason` varchar(255) DEFAULT NULL,
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `FollowUpDate` date DEFAULT NULL,
  `FollowUpTime` time DEFAULT NULL,
  `FollowUpServiceID` int(11) DEFAULT NULL,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`ConsultationID`, `PetID`, `ClientID`, `AppointmentID`, `ConsultationDate`, `VetName`, `ChiefComplaint`, `Diagnosis`, `Treatment`, `Prescription`, `Notes`, `UpdateReason`, `UpdatedAt`, `FollowUpDate`, `FollowUpTime`, `FollowUpServiceID`, `IsDeleted`, `CreatedAt`) VALUES
(1, 1, 1, 10, '2026-06-01', 'Dr. Reyes', 'Routine grooming visit.', 'Healthy, slight dandruff.', 'Medicated shampoo bath.', 'Zinc supplement tabs x30', 'Owner advised monthly grooming.', NULL, '2026-06-08 18:35:42', NULL, NULL, NULL, 0, '2026-06-08 18:35:42'),
(2, 3, 2, 11, '2026-05-25', 'Dr. Santos', 'Annual check. No complaints.', 'Healthy adult dog.', 'Physical exam, weight check.', 'None', 'Advised weight management diet.', NULL, '2026-06-08 18:35:42', NULL, NULL, NULL, 0, '2026-06-08 18:35:42'),
(3, 6, 4, 12, '2026-06-05', 'Dr. Reyes', 'Pre-surgery blood work.', 'Mild anemia, otherwise normal.', 'Iron supplementation for 2 weeks pre-op.', 'Ferrous sulfate 50mg SID x14', 'Surgery scheduled in 3 days.', NULL, '2026-06-08 18:35:42', NULL, NULL, NULL, 0, '2026-06-08 18:35:42'),
(4, 9, 7, 13, '2026-06-03', 'Dr. Lim', 'Full groom.', 'Healthy coat, mild ear debris.', 'Grooming, ear flushing.', 'Ear cleaning solution PRN', '', 'cancelled follow up', '2026-06-11 19:19:36', NULL, NULL, NULL, 1, '2026-06-08 18:35:42'),
(5, 5, 3, NULL, '2026-05-29', 'Dr. Santos', 'Vomiting x2 days, lethargy.', 'Gastroenteritis.', 'IV fluids, anti-emetic injection.', 'Metronidazole 250mg BID x5 days, Cimetidine 200mg BID x5 days', 'Recheck in 5 days if no improvement.', NULL, '2026-06-08 18:35:42', NULL, NULL, NULL, 0, '2026-06-08 18:35:42'),
(6, 8, 6, NULL, '2026-05-19', 'Dr. Reyes', 'Limping on right hind leg x3 days.', 'Mild soft-tissue injury, no fracture.', 'Rest, NSAIDs, cold compress.', 'Meloxicam 1.5mg SID x7 days', 'X-ray confirmed no bone involvement. Follow-up if persists.', NULL, '2026-06-08 18:35:42', NULL, NULL, NULL, 0, '2026-06-08 18:35:42'),
(7, 6, 4, 5, '2026-06-08', 'Dr. Santos', 'Limping On Right Hind Leg X3 Days.', 'Fracture.', 'a', 'a', '', '.', '2026-06-11 18:12:14', NULL, NULL, NULL, 0, '2026-06-08 22:23:16'),
(8, 14, 6, 16, '2026-06-11', 'Dr. Lim', '-', '-', '-', '-', '', 'cancelled follow up', '2026-06-11 10:07:05', NULL, NULL, NULL, 1, '2026-06-11 04:33:09'),
(9, 12, 10, 18, '2026-06-15', 'Dr. Reyes', '-', '-', '-', '-', '', 'added time', '2026-06-12 11:34:36', '2026-06-18', '12:00:00', 1, 0, '2026-06-11 10:08:35'),
(10, 3, 2, 2, '2026-06-08', 'Dr. Santos', '-`', '-', '-', '-', '', NULL, '2026-06-11 10:17:01', NULL, NULL, NULL, 0, '2026-06-11 10:17:01'),
(11, 7, 5, 6, '2026-06-08', 'Dr. Santos', '-', '-', '-', '-', '', NULL, '2026-06-11 10:42:22', NULL, NULL, NULL, 0, '2026-06-11 10:42:22');

-- --------------------------------------------------------

--
-- Table structure for table `consultation_services`
--

CREATE TABLE `consultation_services` (
  `ConsultationServiceID` int(11) NOT NULL,
  `ConsultationID` int(11) NOT NULL,
  `ServiceID` int(11) DEFAULT NULL,
  `ServiceName` varchar(150) NOT NULL,
  `Category` varchar(100) DEFAULT NULL,
  `Quantity` int(11) NOT NULL DEFAULT 1,
  `UnitPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consultation_services`
--

INSERT INTO `consultation_services` (`ConsultationServiceID`, `ConsultationID`, `ServiceID`, `ServiceName`, `Category`, `Quantity`, `UnitPrice`, `Subtotal`, `CreatedAt`) VALUES
(1, 1, 14, 'Grooming – Bath & Blow Dry', 'Grooming', 1, 350.00, 350.00, '2026-06-08 18:35:42'),
(2, 2, 1, 'Annual Wellness Exam', 'Consultation', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(3, 3, 12, 'Blood Chemistry Panel', 'Diagnostics', 1, 1200.00, 1200.00, '2026-06-08 18:35:42'),
(6, 5, 19, 'IV Fluid Therapy', 'Treatment', 1, 800.00, 800.00, '2026-06-08 18:35:42'),
(7, 5, 1, 'Annual Wellness Exam', 'Consultation', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(8, 6, 11, 'X-Ray – Single View', 'Diagnostics', 1, 800.00, 800.00, '2026-06-08 18:35:42'),
(9, 6, 1, 'Annual Wellness Exam', 'Consultation', 1, 500.00, 500.00, '2026-06-08 18:35:42'),
(14, 8, 7, 'Spay (Female)', 'Surgery', 1, 3500.00, 3500.00, '2026-06-11 10:06:58'),
(16, 10, 2, 'Vaccination – Anti-Rabies', 'Vaccination', 1, 350.00, 350.00, '2026-06-11 10:17:01'),
(17, 11, 3, 'Vaccination – 5-in-1 (DHPP)', 'Vaccination', 1, 450.00, 450.00, '2026-06-11 10:42:22'),
(18, 7, 9, 'Dental Cleaning (Scaling)', 'Dental', 1, 1500.00, 1500.00, '2026-06-11 18:12:14'),
(21, 4, 15, 'Grooming – Full Trim', 'Grooming', 1, 600.00, 600.00, '2026-06-11 19:19:28'),
(22, 4, 16, 'Nail Trim', 'Grooming', 1, 150.00, 150.00, '2026-06-11 19:19:28'),
(23, 9, 12, 'Blood Chemistry Panel', 'Diagnostics', 1, 1200.00, 1200.00, '2026-06-12 11:34:36');

-- --------------------------------------------------------

--
-- Table structure for table `lodging`
--

CREATE TABLE `lodging` (
  `LodgingID` int(11) NOT NULL,
  `PetID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `CheckInDate` date NOT NULL,
  `CheckOutDate` date DEFAULT NULL,
  `CageNumber` varchar(30) DEFAULT NULL,
  `DailyRate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `SpecialInstructions` text DEFAULT NULL,
  `Status` enum('Active','Checked Out','Cancelled') NOT NULL DEFAULT 'Active',
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IsDeleted` tinyint(1) DEFAULT 0,
  `UpdateReason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lodging`
--

INSERT INTO `lodging` (`LodgingID`, `PetID`, `ClientID`, `CheckInDate`, `CheckOutDate`, `CageNumber`, `DailyRate`, `SpecialInstructions`, `Status`, `CreatedAt`, `UpdatedAt`, `IsDeleted`, `UpdateReason`) VALUES
(1, 6, 4, '2026-06-06', NULL, 'C6', 600.00, 'Feed 3x daily. No pork.', 'Active', '2026-06-08 18:35:42', '2026-06-09 03:08:47', 0, 'change cage'),
(2, 12, 10, '2026-06-04', NULL, 'C10', 400.00, 'Anxiety — keep away from cats.', 'Active', '2026-06-08 18:35:42', '2026-06-09 03:09:05', 0, 'change cage'),
(3, 3, 2, '2026-06-01', '2026-06-06', 'C8', 600.00, 'Allergic to chicken-based food.', 'Checked Out', '2026-06-08 18:35:42', '2026-06-09 03:09:24', 0, 'change cage'),
(4, 1, 1, '2026-05-29', '2026-06-05', 'C8', 400.00, 'Playful — large run preferred.', 'Checked Out', '2026-06-08 18:35:42', '2026-06-09 03:09:49', 0, 'change cage'),
(5, 8, 6, '2026-06-08', '2026-06-11', 'C1', 450.00, '', 'Checked Out', '2026-06-09 02:56:17', '2026-06-11 11:34:44', 0, 'Change cage');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `UserID`, `token`, `expires_at`, `used`, `created_at`) VALUES
(1, 4, 'd803cc45830d17c8ce5d465a038e127484cce041dff144ed332102c9954101ea', '2026-06-08 23:04:42', 1, '2026-06-08 20:04:42'),
(2, 4, '2585ad9ab4158981a609f91d76fbb38b2d7e1609439aae2222a1ea6534752dd7', '2026-06-08 23:05:30', 0, '2026-06-08 20:05:30');

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `PetID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `PetName` varchar(100) NOT NULL,
  `Species` varchar(50) NOT NULL,
  `Breed` varchar(100) DEFAULT NULL,
  `Gender` enum('Male','Female','Unknown') NOT NULL DEFAULT 'Unknown',
  `DateOfBirth` date DEFAULT NULL,
  `Color` varchar(80) DEFAULT NULL,
  `Weight` decimal(6,2) DEFAULT NULL COMMENT 'Weight in kg',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`PetID`, `ClientID`, `PetName`, `Species`, `Breed`, `Gender`, `DateOfBirth`, `Color`, `Weight`, `IsActive`, `IsDeleted`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 1, 'Choco', 'Dog', 'Aspin', 'Male', '2020-03-15', 'Brown', 8.50, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(2, 1, 'Nala', 'Cat', 'Puspin', 'Female', '2021-06-01', 'White & Orange', 3.20, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(3, 2, 'Bruno', 'Dog', 'German Shepherd', 'Male', '2019-11-20', 'Black & Tan', 32.00, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(4, 3, 'Luna', 'Cat', 'Persian', 'Female', '2022-01-10', 'Gray', 4.00, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(5, 3, 'Kobe', 'Dog', 'Shih Tzu', 'Male', '2021-08-25', 'White & Gold', 5.80, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(6, 4, 'Simba', 'Dog', 'Golden Retriever', 'Male', '2018-07-04', 'Golden', 28.00, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(7, 5, 'Mochi', 'Cat', 'Scottish Fold', 'Female', '2023-02-14', 'Cream', 3.50, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(8, 6, 'Rex', 'Dog', 'Labrador Retriever', 'Male', '2019-05-30', 'Black', 30.00, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(9, 7, 'Pepper', 'Bird', 'Lovebird', 'Female', '2022-09-01', 'Green & Yellow', 0.06, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(10, 8, 'Max', 'Dog', 'Aspin', 'Male', '2020-12-12', 'Brown & White', 10.00, 0, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(11, 9, 'Kitty', 'Cat', 'Puspin', 'Female', '2021-04-20', 'Calico', 3.80, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(12, 10, 'Buddy', 'Dog', 'Beagle', 'Male', '2020-06-18', 'Tri-Color', 11.00, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(13, 4, 'Cleo', 'Rabbit', 'Holland Lop', 'Female', '2023-05-05', 'White', 1.80, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(14, 6, 'Ginger', 'Dog', 'Chow Chow', 'Female', '2020-10-10', 'Red', 22.00, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(15, 9, 'Shadow', 'Cat', 'Maine Coon', 'Male', '2019-12-25', 'Dark Gray', 6.50, 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `ServiceID` int(11) NOT NULL,
  `ServiceName` varchar(150) NOT NULL,
  `Category` varchar(100) DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes',
  `Description` text DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`ServiceID`, `ServiceName`, `Category`, `Price`, `Duration`, `Description`, `IsActive`, `IsDeleted`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'Annual Wellness Exam', 'Consultation', 500.00, 30, 'Comprehensive yearly health check-up for pets.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(2, 'Vaccination – Anti-Rabies', 'Vaccination', 350.00, 15, 'Rabies vaccine as required by Philippine law.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(3, 'Vaccination – 5-in-1 (DHPP)', 'Vaccination', 450.00, 15, 'Distemper, Hepatitis, Parvovirus, Parainfluenza.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(4, 'Vaccination – FVRCP (Cat)', 'Vaccination', 400.00, 15, 'Feline core vaccine — Panleukopenia, Calicivirus, Rhinotracheitis.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(5, 'Deworming', 'Preventive', 200.00, 15, 'Internal parasite treatment.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(6, 'Flea & Tick Treatment', 'Preventive', 300.00, 20, 'Topical or oral anti-parasitic treatment.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(7, 'Spay (Female)', 'Surgery', 3500.00, 90, 'Ovariohysterectomy — surgical sterilisation.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(8, 'Neuter (Male)', 'Surgery', 2500.00, 60, 'Orchiectomy — surgical sterilisation.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(9, 'Dental Cleaning (Scaling)', 'Dental', 1500.00, 60, 'Ultrasonic scaling and polishing under sedation.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(10, 'Tooth Extraction', 'Dental', 800.00, 45, 'Extraction of a single problematic tooth.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(11, 'X-Ray – Single View', 'Diagnostics', 800.00, 20, 'Digital radiograph for injury or illness assessment.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(12, 'Blood Chemistry Panel', 'Diagnostics', 1200.00, 30, 'Comprehensive blood work including CBC and chemistry.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(13, 'Urinalysis', 'Diagnostics', 500.00, 20, 'Urine dipstick and sediment examination.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(14, 'Grooming – Bath & Blow Dry', 'Grooming', 350.00, 60, 'Shampoo, conditioning, blow-dry, and ear cleaning.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(15, 'Grooming – Full Trim', 'Grooming', 600.00, 90, 'Full haircut based on breed standards.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(16, 'Nail Trim', 'Grooming', 150.00, 15, 'Trimming and filing of all nails.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(17, 'Boarding – Daily Rate (Small)', 'Boarding', 400.00, NULL, 'Overnight boarding for pets under 10 kg.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(18, 'Boarding – Daily Rate (Large)', 'Boarding', 600.00, NULL, 'Overnight boarding for pets 10 kg and above.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(19, 'IV Fluid Therapy', 'Treatment', 800.00, 60, 'Intravenous fluid support for dehydration or illness.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42'),
(20, 'Emergency Consultation', 'Consultation', 1000.00, 30, 'Walk-in or after-hours emergency visit.', 1, 0, '2026-06-08 18:35:42', '2026-06-08 18:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `StaffID` varchar(20) NOT NULL,
  `FirstName` varchar(100) NOT NULL,
  `LastName` varchar(100) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `Phone` int(11) DEFAULT NULL,
  `Role` enum('Admin','Veterinarian','Receptionist','Nurse','Other') NOT NULL DEFAULT 'Veterinarian',
  `Password` varchar(255) NOT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `StaffID`, `FirstName`, `LastName`, `Email`, `Phone`, `Role`, `Password`, `IsActive`, `IsDeleted`, `CreatedAt`) VALUES
(3, 'VET-00001', 'Admin', 'Staff', 'admin@gmail.com', 2147483647, 'Admin', '$2y$10$RWcDkHu5vV2C1SaWMPPLBua7nBo5.xVKI9VivaZFvKOSbqiQNgSDS', 1, 0, '2026-06-09 03:56:13'),
(4, 'VET-00002', 'Theo', 'Cruz', 'THEOJOHNCRUZ@GMAIL.COM', 2147483647, 'Admin', '$2y$10$zWT0wROxViG2IRki8VmkBuNM/6kkszE8jykT1qu6iGNS8IdM0Y8Oq', 1, 0, '2026-06-09 04:04:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`AppointmentID`),
  ADD KEY `idx_appt_date` (`AppointmentDate`,`AppointmentTime`),
  ADD KEY `idx_appt_client` (`ClientID`),
  ADD KEY `idx_appt_pet` (`PetID`),
  ADD KEY `idx_appt_service` (`ServiceID`),
  ADD KEY `idx_appt_status` (`Status`,`IsDeleted`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`BillingID`),
  ADD KEY `idx_billing_client` (`ClientID`),
  ADD KEY `idx_billing_pet` (`PetID`),
  ADD KEY `idx_billing_consultation` (`ConsultationID`),
  ADD KEY `idx_billing_date` (`BillingDate`),
  ADD KEY `idx_billing_status` (`PaymentStatus`,`IsDeleted`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`BillingItemID`),
  ADD KEY `idx_billitem_billing` (`BillingID`),
  ADD KEY `idx_billitem_service` (`ServiceID`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`ClientID`),
  ADD KEY `idx_clients_active` (`IsActive`,`IsDeleted`),
  ADD KEY `idx_clients_email` (`Email`),
  ADD KEY `idx_clients_name` (`LastName`,`FirstName`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`ConsultationID`),
  ADD KEY `idx_con_pet` (`PetID`),
  ADD KEY `idx_con_client` (`ClientID`),
  ADD KEY `idx_con_appt` (`AppointmentID`),
  ADD KEY `idx_con_date` (`ConsultationDate`),
  ADD KEY `fk_con_followup_svc` (`FollowUpServiceID`);

--
-- Indexes for table `consultation_services`
--
ALTER TABLE `consultation_services`
  ADD PRIMARY KEY (`ConsultationServiceID`),
  ADD KEY `idx_consvc_consultation` (`ConsultationID`),
  ADD KEY `idx_consvc_service` (`ServiceID`);

--
-- Indexes for table `lodging`
--
ALTER TABLE `lodging`
  ADD PRIMARY KEY (`LodgingID`),
  ADD KEY `idx_lodging_pet` (`PetID`),
  ADD KEY `idx_lodging_client` (`ClientID`),
  ADD KEY `idx_lodging_status` (`Status`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token` (`token`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`PetID`),
  ADD KEY `idx_pets_client` (`ClientID`),
  ADD KEY `idx_pets_active` (`IsActive`,`IsDeleted`),
  ADD KEY `idx_pets_species` (`Species`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`ServiceID`),
  ADD KEY `idx_services_category` (`Category`),
  ADD KEY `idx_services_active` (`IsActive`,`IsDeleted`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `uq_staff_id` (`StaffID`),
  ADD UNIQUE KEY `uq_email` (`Email`),
  ADD KEY `idx_users_active` (`IsActive`,`IsDeleted`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `AppointmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `BillingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `BillingItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `ClientID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `ConsultationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `consultation_services`
--
ALTER TABLE `consultation_services`
  MODIFY `ConsultationServiceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lodging`
--
ALTER TABLE `lodging`
  MODIFY `LodgingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `PetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `ServiceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`ServiceID`) REFERENCES `services` (`ServiceID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `fk_billing_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billing_consult` FOREIGN KEY (`ConsultationID`) REFERENCES `consultations` (`ConsultationID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billing_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD CONSTRAINT `fk_billitem_billing` FOREIGN KEY (`BillingID`) REFERENCES `billing` (`BillingID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billitem_service` FOREIGN KEY (`ServiceID`) REFERENCES `services` (`ServiceID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_con_appt` FOREIGN KEY (`AppointmentID`) REFERENCES `appointments` (`AppointmentID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_con_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_con_followup_svc` FOREIGN KEY (`FollowUpServiceID`) REFERENCES `services` (`ServiceID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_con_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`) ON UPDATE CASCADE;

--
-- Constraints for table `consultation_services`
--
ALTER TABLE `consultation_services`
  ADD CONSTRAINT `fk_consvc_consultation` FOREIGN KEY (`ConsultationID`) REFERENCES `consultations` (`ConsultationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consvc_service` FOREIGN KEY (`ServiceID`) REFERENCES `services` (`ServiceID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `lodging`
--
ALTER TABLE `lodging`
  ADD CONSTRAINT `fk_lodging_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lodging_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`) ON UPDATE CASCADE;

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `fk_pets_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
