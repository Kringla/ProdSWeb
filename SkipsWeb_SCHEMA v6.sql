-- --------------------------------------------------------
-- Host:                         localhost
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table skipsdb.tblfartnavn
CREATE TABLE IF NOT EXISTS `tblfartnavn` (
  `FartNavn_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FartObj_ID` int(11) NOT NULL DEFAULT 1,
  `FartNavn` varchar(100) DEFAULT NULL,
  `FartType_ID` int(11) DEFAULT NULL,
  `PennantTiln` varchar(50) DEFAULT NULL,
  `TidlNavn` varchar(255) DEFAULT NULL,
  `FartNotater` mediumtext DEFAULT NULL,
  KEY `FartNavn_ID` (`FartNavn_ID`),
  KEY `FartObj_ID` (`FartObj_ID`),
  KEY `FartTypeNavn` (`FartType_ID`),
  KEY `ix_FartNavn_FartNavn` (`FartNavn`),
  KEY `FartoyNavnObjektID` (`FartNavn`,`FartObj_ID`),
  CONSTRAINT `FartTypeNavn` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  CONSTRAINT `ObjID` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblfartobj
CREATE TABLE IF NOT EXISTS `tblfartobj` (
  `FartObj_ID` int(11) NOT NULL AUTO_INCREMENT,
  `NavnObj` varchar(100) DEFAULT NULL,
  `FartType_ID` int(11) DEFAULT 1,
  `IMO` int(11) DEFAULT NULL,
  `Kontrahert` varchar(255) DEFAULT NULL,
  `Kjolstrukket` varchar(255) DEFAULT NULL,
  `Sjosatt` varchar(255) DEFAULT NULL,
  `Levert` varchar(255) DEFAULT NULL,
  `Bygget` varchar(255) DEFAULT NULL,
  `LeverID` int(11) DEFAULT 1,
  `SkrogID` int(11) DEFAULT 1,
  `BnrSkrog` varchar(255) DEFAULT NULL,
  `StroketYear` smallint(6) DEFAULT NULL,
  `StroketID` int(11) DEFAULT NULL,
  `Historikk` text DEFAULT NULL,
  `ObjNotater` mediumtext DEFAULT NULL,
  `IngenData` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`FartObj_ID`),
  KEY `LeverID` (`LeverID`),
  KEY `SkrogID` (`SkrogID`),
  KEY `FartTypeObj` (`FartType_ID`),
  CONSTRAINT `FartTypeObj` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblfartspes
CREATE TABLE IF NOT EXISTS `tblfartspes` (
  `FartSpes_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FartObj_ID` int(11) NOT NULL DEFAULT 1,
  `YearSpes` smallint(6) DEFAULT NULL,
  `MndSpes` tinyint(4) DEFAULT NULL,
  `Verft_ID` int(11) DEFAULT NULL,
  `Byggenr` varchar(255) DEFAULT NULL,
  `SkrogID` int(11) DEFAULT NULL,
  `BnrSkrog` varchar(255) DEFAULT NULL,
  `Materiale` varchar(255) DEFAULT NULL,
  `FartMat_ID` int(11) DEFAULT NULL,
  `FartType_ID` int(11) DEFAULT NULL,
  `FartFunk_ID` int(11) DEFAULT NULL,
  `FartSkrog_ID` int(11) DEFAULT NULL,
  `FartDrift_ID` int(11) DEFAULT NULL,
  `FunkDetalj` varchar(255) DEFAULT NULL,
  `TeknDetalj` varchar(255) DEFAULT NULL,
  `FartKlasse_ID` int(11) DEFAULT 1,
  `Fartklasse` varchar(255) DEFAULT NULL,
  `Kapasitet` varchar(255) DEFAULT NULL,
  `Rigg` varchar(50) DEFAULT NULL,
  `FartRigg_ID` int(11) DEFAULT 1,
  `FartMotor_ID` int(11) DEFAULT 1,
  `MotorDetalj` varchar(50) DEFAULT NULL,
  `MotorEff` varchar(20) DEFAULT NULL,
  `MaxFart` smallint(6) DEFAULT NULL,
  `Lengde` smallint(6) DEFAULT NULL,
  `Bredde` smallint(6) DEFAULT NULL,
  `Dypg` smallint(6) DEFAULT NULL,
  `Tonnasje` varchar(255) DEFAULT NULL,
  `Drektigh` varchar(255) DEFAULT NULL,
  `TonnEnh_ID` int(11) DEFAULT NULL,
  `Objekt` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`FartSpes_ID`),
  KEY `FartObj_ID` (`FartObj_ID`),
  KEY `Verft_ID` (`Verft_ID`),
  KEY `SkrogID` (`SkrogID`),
  KEY `FartTypeSpes` (`FartType_ID`),
  KEY `VerftObj` (`Verft_ID`,`FartObj_ID`),
  CONSTRAINT `FartTypeSpes` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  CONSTRAINT `SkrogSpes` FOREIGN KEY (`SkrogID`) REFERENCES `tblverft` (`Verft_ID`),
  CONSTRAINT `VerftSpes` FOREIGN KEY (`Verft_ID`) REFERENCES `tblverft` (`Verft_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblfarttid
CREATE TABLE IF NOT EXISTS `tblfarttid` (
  `FartTid_ID` int(11) NOT NULL AUTO_INCREMENT,
  `YearTid` smallint(6) DEFAULT NULL,
  `MndTid` tinyint(4) DEFAULT NULL,
  `FartObj_ID` int(11) DEFAULT NULL,
  `FartNavn_ID` int(11) DEFAULT NULL,
  `FartSpes_ID` int(11) DEFAULT NULL,
  `Objekt` tinyint(1) DEFAULT NULL,
  `Rederi` varchar(255) DEFAULT NULL,
  `Nasjon_ID` int(11) DEFAULT NULL,
  `RegHavn` varchar(50) DEFAULT NULL,
  `MMSI` varchar(15) DEFAULT NULL,
  `Kallesignal` varchar(15) DEFAULT NULL,
  `Fiskerinr` varchar(255) DEFAULT NULL,
  `Navning` tinyint(1) DEFAULT NULL,
  `Eierskifte` tinyint(1) DEFAULT NULL,
  `Annet` tinyint(1) DEFAULT NULL,
  `Hendelse` varchar(255) DEFAULT NULL,
  `Historie` mediumtext DEFAULT NULL,
  PRIMARY KEY (`FartTid_ID`),
  KEY `FartObj_ID` (`FartObj_ID`),
  KEY `FartNavn_ID` (`FartNavn_ID`),
  KEY `FartSpes_ID` (`FartSpes_ID`),
  KEY `Nasjon_ID` (`Nasjon_ID`),
  KEY `ix_FartTid_Navn_Tid` (`FartNavn_ID`,`YearTid`,`MndTid`,`FartTid_ID`),
  KEY `ix_FartTid_Navn_Obj` (`FartNavn_ID`,`Objekt`,`FartObj_ID`),
  KEY `ix_FartTid_Nasjon` (`Nasjon_ID`),
  KEY `RederiObj` (`Rederi`,`FartObj_ID`),
  KEY `ObjTidID` (`FartObj_ID`,`FartTid_ID`),
  CONSTRAINT `FartIDTid` FOREIGN KEY (`FartNavn_ID`) REFERENCES `tblfartnavn` (`FartNavn_ID`),
  CONSTRAINT `NasjonIDTid` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`),
  CONSTRAINT `ObjIDTid` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`),
  CONSTRAINT `SpesIDTid` FOREIGN KEY (`FartSpes_ID`) REFERENCES `tblfartspes` (`FartSpes_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblverft
CREATE TABLE IF NOT EXISTS `tblverft` (
  `Verft_ID` int(11) NOT NULL AUTO_INCREMENT,
  `VerftNavn` varchar(255) DEFAULT NULL,
  `Sted` varchar(255) DEFAULT NULL,
  `Nasjon_ID` int(11) DEFAULT NULL,
  `TidlID` int(11) DEFAULT NULL,
  `Etablert` smallint(6) DEFAULT NULL,
  `Nedlagt` smallint(6) DEFAULT NULL,
  `EtterID` int(11) DEFAULT NULL,
  `Merknad` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`Verft_ID`) USING BTREE,
  KEY `Nasjon_ID` (`Nasjon_ID`),
  KEY `VerftSted` (`VerftNavn`,`Sted`),
  CONSTRAINT `NasjonIDVerft` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblxdigmuseum
CREATE TABLE IF NOT EXISTS `tblxdigmuseum` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FartNavn_ID` int(11) NOT NULL,
  `DIMUkode` varchar(15) DEFAULT '0',
  `NSMkode` varchar(15) NOT NULL,
  `Motiv` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FartNavnDIMU` (`FartNavn_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblxfartlink
CREATE TABLE IF NOT EXISTS `tblxfartlink` (
  `FartLk_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FartNavn_ID` int(11) NOT NULL DEFAULT 1,
  `LinkType_ID` int(11) DEFAULT NULL,
  `LinkType` varchar(50) DEFAULT NULL,
  `LinkInnh` varchar(50) DEFAULT NULL,
  `Link` mediumtext DEFAULT NULL,
  `SerNo` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`FartLk_ID`) USING BTREE,
  KEY `FartID` (`FartNavn_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblxverftlink
CREATE TABLE IF NOT EXISTS `tblxverftlink` (
  `VerftLk_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Verft_ID` int(11) NOT NULL DEFAULT 1,
  `LinkType_ID` int(11) DEFAULT NULL,
  `LinkType` varchar(50) DEFAULT NULL,
  `LinkInnh` varchar(50) DEFAULT NULL,
  `Link` mediumtext DEFAULT NULL,
  PRIMARY KEY (`VerftLk_ID`),
  KEY `Verft_ID` (`Verft_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartdrift
CREATE TABLE IF NOT EXISTS `tblzfartdrift` (
  `FartDrift_ID` int(11) NOT NULL AUTO_INCREMENT,
  `DriftMiddel` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`FartDrift_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartfunk
CREATE TABLE IF NOT EXISTS `tblzfartfunk` (
  `FartFunk_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TypeFunksjon` varchar(255) DEFAULT NULL,
  `FunkDet` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`FartFunk_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartklasse
CREATE TABLE IF NOT EXISTS `tblzfartklasse` (
  `FartKlasse_ID` int(11) NOT NULL AUTO_INCREMENT,
  `KlasseNavn` varchar(255) DEFAULT NULL,
  `TypeKlasse` varchar(255) DEFAULT NULL,
  `Klasse` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`FartKlasse_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartmat
CREATE TABLE IF NOT EXISTS `tblzfartmat` (
  `FartMat_ID` int(11) NOT NULL AUTO_INCREMENT,
  `MatFork` varchar(50) DEFAULT NULL,
  `Materiale` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`FartMat_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartmotor
CREATE TABLE IF NOT EXISTS `tblzfartmotor` (
  `FartMotor_ID` int(11) NOT NULL AUTO_INCREMENT,
  `MotorFork` varchar(10) DEFAULT NULL,
  `MotorDetalj` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`FartMotor_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartrigg
CREATE TABLE IF NOT EXISTS `tblzfartrigg` (
  `FartRigg_ID` int(11) NOT NULL AUTO_INCREMENT,
  `RiggFork` varchar(3) DEFAULT NULL,
  `RiggDetalj` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`FartRigg_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfartskrog
CREATE TABLE IF NOT EXISTS `tblzfartskrog` (
  `FartSkrog_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TypeSkrog` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`FartSkrog_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzfarttype
CREATE TABLE IF NOT EXISTS `tblzfarttype` (
  `FartType_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TypeFork` varchar(3) DEFAULT NULL,
  `FartType` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`FartType_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzlinktype
CREATE TABLE IF NOT EXISTS `tblzlinktype` (
  `LinkType_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LinkType` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`LinkType_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblznasjon
CREATE TABLE IF NOT EXISTS `tblznasjon` (
  `Nasjon_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Nasjon` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`Nasjon_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzstroket
CREATE TABLE IF NOT EXISTS `tblzstroket` (
  `Stroket_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Strok` varchar(255) DEFAULT NULL,
  `StrokDetalj` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`Stroket_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblztonnenh
CREATE TABLE IF NOT EXISTS `tblztonnenh` (
  `TonnEnh_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TonnFork` varchar(5) DEFAULT NULL,
  `TonnDetalj` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`TonnEnh_ID`),
  UNIQUE KEY `TonnFork` (`TonnFork`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsdb.tblzuser
CREATE TABLE IF NOT EXISTS `tblzuser` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `LastUsed` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
