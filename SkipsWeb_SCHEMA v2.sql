-- --------------------------------------------------------
-- Host:                         hostmaster.onnet.no
-- Server version:               8.0.39-cll-lve - MySQL Community Server - GPL
-- Server OS:                    Linux
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

-- Dumping structure for table skipsweb_skipsdb.tblfartnavn
CREATE TABLE IF NOT EXISTS `tblfartnavn` (
  `FartNavn_ID` int NOT NULL AUTO_INCREMENT,
  `FartObj_ID` int NOT NULL DEFAULT '1',
  `FartNavn` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartType_ID` int DEFAULT NULL,
  `PennantTiln` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TidlNavn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartNotater` mediumtext COLLATE utf8mb4_unicode_ci,
  KEY `FartNavn_ID` (`FartNavn_ID`),
  KEY `FartObj_ID` (`FartObj_ID`),
  KEY `FartTypeNavn` (`FartType_ID`),
  KEY `ix_FartNavn_FartNavn` (`FartNavn`),
  KEY `FartoyNavnObjektID` (`FartNavn`,`FartObj_ID`),
  CONSTRAINT `FartTypeNavn` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`),
  CONSTRAINT `ObjID` FOREIGN KEY (`FartObj_ID`) REFERENCES `tblfartobj` (`FartObj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblfartobj
CREATE TABLE IF NOT EXISTS `tblfartobj` (
  `FartObj_ID` int NOT NULL AUTO_INCREMENT,
  `NavnObj` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartType_ID` int DEFAULT '1',
  `IMO` int DEFAULT NULL,
  `Kontrahert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Kjolstrukket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Sjosatt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Levert` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Bygget` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LeverID` int DEFAULT '1',
  `SkrogID` int DEFAULT '1',
  `BnrSkrog` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `StroketYear` smallint DEFAULT NULL,
  `StroketID` int DEFAULT NULL,
  `Historikk` text COLLATE utf8mb4_unicode_ci,
  `ObjNotater` mediumtext COLLATE utf8mb4_unicode_ci,
  `IngenData` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`FartObj_ID`),
  KEY `LeverID` (`LeverID`),
  KEY `SkrogID` (`SkrogID`),
  KEY `FartTypeObj` (`FartType_ID`),
  CONSTRAINT `FartTypeObj` FOREIGN KEY (`FartType_ID`) REFERENCES `tblzfarttype` (`FartType_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblfartspes
CREATE TABLE IF NOT EXISTS `tblfartspes` (
  `FartSpes_ID` int NOT NULL AUTO_INCREMENT,
  `FartObj_ID` int NOT NULL DEFAULT '1',
  `YearSpes` smallint DEFAULT NULL,
  `MndSpes` tinyint DEFAULT NULL,
  `Verft_ID` int DEFAULT NULL,
  `Byggenr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SkrogID` int DEFAULT NULL,
  `BnrSkrog` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Materiale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartMat_ID` int DEFAULT NULL,
  `FartType_ID` int DEFAULT NULL,
  `FartFunk_ID` int DEFAULT NULL,
  `FartSkrog_ID` int DEFAULT NULL,
  `FartDrift_ID` int DEFAULT NULL,
  `FunkDetalj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TeknDetalj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartKlasse_ID` int DEFAULT NULL,
  `Fartklasse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Kapasitet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Rigg` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FartRigg_ID` int DEFAULT NULL,
  `FartMotor_ID` int DEFAULT NULL,
  `MotorDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MotorEff` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MaxFart` smallint DEFAULT NULL,
  `Lengde` smallint DEFAULT NULL,
  `Bredde` smallint DEFAULT NULL,
  `Dypg` smallint DEFAULT NULL,
  `Tonnasje` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Drektigh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TonnEnh_ID` int DEFAULT NULL,
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

-- Dumping structure for table skipsweb_skipsdb.tblfarttid
CREATE TABLE IF NOT EXISTS `tblfarttid` (
  `FartTid_ID` int NOT NULL AUTO_INCREMENT,
  `YearTid` smallint DEFAULT NULL,
  `MndTid` tinyint DEFAULT NULL,
  `FartObj_ID` int DEFAULT NULL,
  `FartNavn_ID` int DEFAULT NULL,
  `FartSpes_ID` int DEFAULT NULL,
  `Objekt` tinyint(1) DEFAULT NULL,
  `Rederi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Nasjon_ID` int DEFAULT NULL,
  `RegHavn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MMSI` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Kallesignal` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Fiskerinr` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Navning` tinyint(1) DEFAULT NULL,
  `Eierskifte` tinyint(1) DEFAULT NULL,
  `Annet` tinyint(1) DEFAULT NULL,
  `Hendelse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Historie` mediumtext COLLATE utf8mb4_unicode_ci,
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

-- Dumping structure for table skipsweb_skipsdb.tblverft
CREATE TABLE IF NOT EXISTS `tblverft` (
  `Verft_ID` int NOT NULL AUTO_INCREMENT,
  `VerftNavn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Sted` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Nasjon_ID` int DEFAULT NULL,
  `TidlID` int DEFAULT NULL,
  `Etablert` smallint DEFAULT NULL,
  `Nedlagt` smallint DEFAULT NULL,
  `EtterID` int DEFAULT NULL,
  `Merknad` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`Verft_ID`) USING BTREE,
  KEY `Nasjon_ID` (`Nasjon_ID`),
  KEY `VerftSted` (`VerftNavn`,`Sted`),
  CONSTRAINT `NasjonIDVerft` FOREIGN KEY (`Nasjon_ID`) REFERENCES `tblznasjon` (`Nasjon_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblxdigmuseum
CREATE TABLE IF NOT EXISTS `tblxdigmuseum` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `FartNavn_ID` int NOT NULL,
  `DIMUkode` varchar(15) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `FartID` (`FartNavn_ID`),
  CONSTRAINT `FartNavnDIMU` FOREIGN KEY (`FartNavn_ID`) REFERENCES `tblfartnavn` (`FartNavn_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblxfartlink
CREATE TABLE IF NOT EXISTS `tblxfartlink` (
  `FartLk_ID` int NOT NULL AUTO_INCREMENT,
  `FartID` int NOT NULL DEFAULT '1',
  `LinkType_ID` int DEFAULT NULL,
  `LinkType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LinkInnh` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Link` mediumtext COLLATE utf8mb4_unicode_ci,
  `SerNo` smallint DEFAULT NULL,
  PRIMARY KEY (`FartLk_ID`) USING BTREE,
  KEY `FartID` (`FartID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblxverftlink
CREATE TABLE IF NOT EXISTS `tblxverftlink` (
  `VerftLk_ID` int NOT NULL AUTO_INCREMENT,
  `Verft_ID` int NOT NULL DEFAULT '1',
  `LinkType_ID` int DEFAULT NULL,
  `LinkType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LinkInnh` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Link` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`VerftLk_ID`),
  KEY `Verft_ID` (`Verft_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartdrift
CREATE TABLE IF NOT EXISTS `tblzfartdrift` (
  `FartDrift_ID` int NOT NULL AUTO_INCREMENT,
  `DriftMiddel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FartDrift_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartfunk
CREATE TABLE IF NOT EXISTS `tblzfartfunk` (
  `FartFunk_ID` int NOT NULL AUTO_INCREMENT,
  `TypeFunksjon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FunkDet` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`FartFunk_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartklasse
CREATE TABLE IF NOT EXISTS `tblzfartklasse` (
  `FartKlasse_ID` int NOT NULL AUTO_INCREMENT,
  `Klasse` tinyint(1) DEFAULT NULL,
  `TypeKlasse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TypeKlasseNavn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FartKlasse_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartmat
CREATE TABLE IF NOT EXISTS `tblzfartmat` (
  `FartMat_ID` int NOT NULL AUTO_INCREMENT,
  `MatFork` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Materiale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FartMat_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartmotor
CREATE TABLE IF NOT EXISTS `tblzfartmotor` (
  `FartMotor_ID` int NOT NULL AUTO_INCREMENT,
  `MotorFork` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MotorDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FartMotor_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartrigg
CREATE TABLE IF NOT EXISTS `tblzfartrigg` (
  `FartRigg_ID` int NOT NULL AUTO_INCREMENT,
  `RiggFork` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `RiggDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FartRigg_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfartskrog
CREATE TABLE IF NOT EXISTS `tblzfartskrog` (
  `FartSkrog_ID` int NOT NULL AUTO_INCREMENT,
  `TypeSkrog` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`FartSkrog_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzfarttype
CREATE TABLE IF NOT EXISTS `tblzfarttype` (
  `FartType_ID` int NOT NULL AUTO_INCREMENT,
  `typefork` varchar(3) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`FartType_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzlinktype
CREATE TABLE IF NOT EXISTS `tblzlinktype` (
  `LinkType_ID` int NOT NULL AUTO_INCREMENT,
  `LinkType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`LinkType_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblznasjon
CREATE TABLE IF NOT EXISTS `tblznasjon` (
  `Nasjon_ID` int NOT NULL AUTO_INCREMENT,
  `Nasjon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`Nasjon_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzstroket
CREATE TABLE IF NOT EXISTS `tblzstroket` (
  `Stroket_ID` int NOT NULL AUTO_INCREMENT,
  `Strok` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `StrokDetalj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`Stroket_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblztonnenh
CREATE TABLE IF NOT EXISTS `tblztonnenh` (
  `TonnEnh_ID` int NOT NULL AUTO_INCREMENT,
  `TonnFork` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TonnDetalj` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`TonnEnh_ID`),
  UNIQUE KEY `TonnFork` (`TonnFork`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table skipsweb_skipsdb.tblzuser
CREATE TABLE IF NOT EXISTS `tblzuser` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `IsActive` tinyint(1) NOT NULL DEFAULT '1',
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
