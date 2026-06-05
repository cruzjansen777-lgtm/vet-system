-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2026 at 03:52 PM
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
  `Reason` text DEFAULT NULL,
  `Status` enum('Scheduled','Completed','Cancelled','No-Show') DEFAULT 'Scheduled',
  `Notes` text DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`AppointmentID`, `PetID`, `ClientID`, `ServiceID`, `AppointmentDate`, `AppointmentTime`, `Reason`, `Status`, `Notes`, `CreatedAt`, `IsDeleted`) VALUES
(1, 1, 1, NULL, '2026-05-26', '14:30:00', 'Annual consultation', 'Completed', NULL, '2026-05-26 20:03:59', 0),
(2, 1, 1, NULL, '2026-05-28', '09:30:00', 'Follow-up consultation', 'Scheduled', NULL, '2026-05-26 20:36:46', 1),
(3, 1, 1, NULL, '2026-05-31', '15:00:00', 'Follow-up consultation', 'Scheduled', NULL, '2026-05-26 20:39:12', 1),
(4, 1, 1, NULL, '2026-05-31', '15:00:00', 'Follow-up consultation', 'Scheduled', NULL, '2026-05-26 20:59:04', 1),
(5, 1, 1, NULL, '2026-05-30', '15:00:00', 'Follow-up consultation', 'No-Show', NULL, '2026-05-26 21:02:08', 1),
(6, 2, 2, NULL, '2026-06-08', '13:00:00', '', 'Scheduled', NULL, '2026-05-26 21:08:31', 0);

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `BillingID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `PetID` int(11) DEFAULT NULL,
  `ConsultationID` int(11) DEFAULT NULL,
  `LodgingID` int(11) DEFAULT NULL,
  `TotalAmount` decimal(10,2) NOT NULL,
  `Discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `AmountPaid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `PaymentMethod` enum('Cash','Card','GCash','PayMaya','Bank Transfer','Other') DEFAULT 'Cash',
  `PaymentStatus` enum('Pending','Partial','Paid') DEFAULT 'Pending',
  `BillingDate` date NOT NULL,
  `Notes` text DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`BillingID`, `ClientID`, `PetID`, `ConsultationID`, `LodgingID`, `TotalAmount`, `Discount`, `AmountPaid`, `PaymentMethod`, `PaymentStatus`, `BillingDate`, `Notes`, `CreatedAt`, `IsDeleted`) VALUES
(1, 1, 1, NULL, NULL, 100.00, 0.00, 5000.00, 'Cash', 'Partial', '2026-05-26', 'Down payment', '2026-05-26 21:09:36', 0),
(2, 2, NULL, NULL, NULL, 100.00, 0.00, 50.00, 'Cash', 'Paid', '2026-05-26', 'Half payment', '2026-05-26 21:18:58', 0);

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `ItemID` int(11) NOT NULL,
  `BillingID` int(11) NOT NULL,
  `ServiceID` int(11) DEFAULT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Quantity` int(11) NOT NULL DEFAULT 1,
  `UnitPrice` decimal(10,2) DEFAULT NULL,
  `Subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `ClientID` int(11) NOT NULL,
  `FirstName` varchar(100) NOT NULL,
  `LastName` varchar(100) NOT NULL,
  `Email` varchar(150) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `IsDeleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`ClientID`, `FirstName`, `LastName`, `Email`, `Phone`, `Address`, `IsActive`, `CreatedAt`, `IsDeleted`) VALUES
(1, 'Juan', 'Dela Cruz', 'juan@email.com', '09123456789', 'Manila', 1, '2026-05-26 19:58:13', 0),
(2, 'Maria', 'Santos', 'msantos@gmail.com', '09123456789', 'Quezon City', 1, '2026-05-26 21:07:27', 0);

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `ConsultationID` int(11) NOT NULL,
  `AppointmentID` int(11) DEFAULT NULL,
  `PetID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `ConsultationDate` date NOT NULL,
  `VetName` varchar(150) DEFAULT NULL,
  `ChiefComplaint` text DEFAULT NULL,
  `Diagnosis` text DEFAULT NULL,
  `Treatment` text DEFAULT NULL,
  `Prescription` text DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `FollowUpDate` date DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`ConsultationID`, `AppointmentID`, `PetID`, `ClientID`, `ConsultationDate`, `VetName`, `ChiefComplaint`, `Diagnosis`, `Treatment`, `Prescription`, `Notes`, `FollowUpDate`, `CreatedAt`, `IsDeleted`) VALUES
(1, NULL, 1, 1, '2026-05-26', 'Dr. Julia Reyes', 'Sample Iinput', 'Sample Iinput', 'Sample Iinput', 'Sample Iinput', '', '2026-05-30', '2026-05-26 20:36:46', 0),
(2, NULL, 1, 1, '2026-05-26', 'Julia Reyes', 'Sample Input', 'Sample Input', 'Sample Input', 'Sample Input', '', NULL, '2026-05-26 20:39:12', 1);

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
  `CageNumber` varchar(20) DEFAULT NULL,
  `DailyRate` decimal(10,2) DEFAULT NULL,
  `SpecialInstructions` text DEFAULT NULL,
  `Status` enum('Active','Checked Out','Cancelled') DEFAULT 'Active',
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `PetID` int(11) NOT NULL,
  `ClientID` int(11) NOT NULL,
  `PetName` varchar(100) NOT NULL,
  `Species` varchar(50) DEFAULT NULL,
  `Breed` varchar(100) DEFAULT NULL,
  `Gender` enum('Male','Female','Unknown') DEFAULT 'Unknown',
  `DateOfBirth` date DEFAULT NULL,
  `Color` varchar(50) DEFAULT NULL,
  `Weight` decimal(5,2) DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `IsDeleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`PetID`, `ClientID`, `PetName`, `Species`, `Breed`, `Gender`, `DateOfBirth`, `Color`, `Weight`, `IsActive`, `CreatedAt`, `IsDeleted`) VALUES
(1, 1, 'Yumi', 'Cat', 'Cat', 'Female', '2025-08-04', 'Gray White', 5.00, 1, '2026-05-26 20:02:16', 0),
(2, 2, 'Tweety', 'Bird', 'Parrot', 'Female', '2025-12-17', 'Red/Green/Yellow', 3.00, 1, '2026-05-26 21:07:46', 0);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `ServiceID` int(11) NOT NULL,
  `ServiceName` varchar(150) NOT NULL,
  `Category` enum('Consultation','Grooming','Surgery','Vaccination','Lodging','Laboratory','Other') DEFAULT 'Other',
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsDeleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`ServiceID`, `ServiceName`, `Category`, `Description`, `Price`, `Duration`, `IsActive`, `IsDeleted`) VALUES
(1, 'General Consultation', 'Consultation', 'Check up routine', 500.00, 100, 1, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`AppointmentID`),
  ADD KEY `fk_appt_pet` (`PetID`),
  ADD KEY `fk_appt_client` (`ClientID`),
  ADD KEY `fk_appt_service` (`ServiceID`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`BillingID`),
  ADD KEY `fk_billing_client` (`ClientID`),
  ADD KEY `fk_billing_pet` (`PetID`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`ItemID`),
  ADD KEY `fk_item_billing` (`BillingID`),
  ADD KEY `fk_item_service` (`ServiceID`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`ClientID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`ConsultationID`),
  ADD KEY `fk_consult_appt` (`AppointmentID`),
  ADD KEY `fk_consult_pet` (`PetID`),
  ADD KEY `fk_consult_client` (`ClientID`);

--
-- Indexes for table `lodging`
--
ALTER TABLE `lodging`
  ADD PRIMARY KEY (`LodgingID`),
  ADD KEY `fk_lodging_pet` (`PetID`),
  ADD KEY `fk_lodging_client` (`ClientID`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`PetID`),
  ADD KEY `fk_pets_client` (`ClientID`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`ServiceID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `AppointmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `BillingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `ItemID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `ClientID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `ConsultationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lodging`
--
ALTER TABLE `lodging`
  MODIFY `LodgingID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `PetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `ServiceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`),
  ADD CONSTRAINT `fk_appt_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`),
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`ServiceID`) REFERENCES `services` (`ServiceID`);

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `fk_billing_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`),
  ADD CONSTRAINT `fk_billing_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`);

--
-- Constraints for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD CONSTRAINT `fk_item_billing` FOREIGN KEY (`BillingID`) REFERENCES `billing` (`BillingID`),
  ADD CONSTRAINT `fk_item_service` FOREIGN KEY (`ServiceID`) REFERENCES `services` (`ServiceID`);

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consult_appt` FOREIGN KEY (`AppointmentID`) REFERENCES `appointments` (`AppointmentID`),
  ADD CONSTRAINT `fk_consult_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`),
  ADD CONSTRAINT `fk_consult_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`);

--
-- Constraints for table `lodging`
--
ALTER TABLE `lodging`
  ADD CONSTRAINT `fk_lodging_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`),
  ADD CONSTRAINT `fk_lodging_pet` FOREIGN KEY (`PetID`) REFERENCES `pets` (`PetID`);

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `fk_pets_client` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
