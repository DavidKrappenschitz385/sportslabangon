-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 07:32 AM
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
-- Database: `leagues`
--

-- --------------------------------------------------------

--
-- Table structure for table `leagues`
--

CREATE TABLE `leagues` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sport_id` int(11) NOT NULL,
  `season` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `registration_deadline` date NOT NULL,
  `max_teams` int(11) DEFAULT 16,
  `rules` text DEFAULT NULL,
  `status` enum('draft','open','closed','active','completed') DEFAULT 'draft',
  `approval_required` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `points_per_win` float DEFAULT 1,
  `points_per_draw` float DEFAULT 0.5,
  `points_per_loss` float DEFAULT 0,
  `league_type` enum('round_robin','single_elimination','double_elimination') DEFAULT 'round_robin',
  `round_robin_rounds` int(11) DEFAULT 1,
  `knockout_teams` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leagues`
--

INSERT INTO `leagues` (`id`, `name`, `sport_id`, `season`, `start_date`, `end_date`, `registration_deadline`, `max_teams`, `rules`, `status`, `approval_required`, `created_by`, `created_at`) VALUES
(1, 'Marc', 2, '2', '2025-08-20', '2025-08-30', '2025-08-14', 14, 'foul sunggo', 'draft', 0, 1, '2025-08-09 05:35:31'),
(2, 'Marc', 2, '2', '2025-08-20', '2025-08-30', '2025-08-14', 14, 'foul sunggo', 'draft', 0, 1, '2025-08-09 05:35:35'),
(4, 'wqewqe', 1, 'wqewqe', '2025-08-28', '2025-09-04', '2025-08-14', 16, 'wewe', 'draft', 0, 1, '2025-08-09 20:13:50'),
(11, 'Sugbo Cup League', 2, '12', '2025-08-29', '2025-09-03', '2025-08-23', 15, 'No hard feelings', 'draft', 0, 1, '2025-08-21 13:40:20'),
(13, 'Sugbo Cup League', 2, '12', '2025-08-29', '2025-09-03', '2025-08-23', 15, 'No hard feelings', 'active', 0, 1, '2025-08-21 13:41:43'),
(15, 'SK LEAGUE FOR NEWBIES', 2, '1', '2025-09-11', '2025-09-27', '2025-09-27', 10, 'JUST PLAY FOR FUN', 'active', 0, 1, '2025-09-09 01:11:50'),
(16, 'Edu rama', 2, '2', '2025-10-30', '2025-12-29', '2025-10-07', 16, 'wew', 'open', 0, 1, '2025-09-30 14:06:25'),
(17, 'Ball Club League Buhisanon', 2, '1', '2025-11-01', '2025-12-31', '2025-10-09', 12, 'for fun', 'open', 0, 1, '2025-10-02 12:59:21'),
(18, 'Labangon SK League 2025', 2, '1', '2025-11-02', '2026-01-01', '2025-10-10', 20, 'Open for labangon residents only', 'open', 0, 1, '2025-10-03 17:30:12'),
(19, 'Buhisanon League', 3, '1', '2025-11-03', '2026-01-02', '2025-10-11', 16, '1qwq', 'open', 0, 1, '2025-10-04 06:00:22'),
(20, 'ZZZZ', 1, '2', '2025-11-03', '2026-01-02', '2025-10-11', 16, '', 'open', 1, 1, '2025-10-04 16:41:55'),
(21, 'CEC Cesafi', 2, '1', '2025-11-03', '2026-01-02', '2025-10-11', 16, '12', 'open', 1, 1, '2025-10-04 17:22:30'),
(22, 'NBA', 2, '12', '2025-11-04', '2026-01-03', '2025-10-12', 16, 'STRONG', 'open', 1, 1, '2025-10-05 15:40:09'),
(23, 'EDU RAMA LEAGUE', 2, '12', '2025-11-10', '2026-01-09', '2025-10-18', 16, 'WEWE', 'open', 1, 1, '2025-10-11 04:39:29'),
(24, 'CEC BASKETBALL LEAGUE', 2, '1', '2025-11-15', '2026-01-14', '2025-10-23', 13, 'play for fun', 'open', 1, 1, '2025-10-16 02:16:25');

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `home_team_id` int(11) NOT NULL,
  `away_team_id` int(11) NOT NULL,
  `round` int(11) DEFAULT NULL,
  `match_num` int(11) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `match_date` datetime NOT NULL,
  `home_score` int(11) DEFAULT NULL,
  `away_score` int(11) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled','postponed') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `match_type` enum('round_robin','quarter_final','semi_final','final','playoff') DEFAULT 'round_robin',
  `bracket_pos` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 05:06:56'),
(2, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 05:17:01'),
(3, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 05:19:57'),
(4, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 05:20:04'),
(5, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 05:23:01'),
(6, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 05:24:45'),
(7, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 05:27:04'),
(8, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 05:27:12'),
(9, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 05:46:07'),
(10, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 06:34:38'),
(11, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 15:53:20'),
(12, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 15:54:41'),
(13, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 15:54:57'),
(14, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 15:55:25'),
(15, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 15:55:32'),
(16, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 15:58:40'),
(17, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 16:01:27'),
(18, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 16:05:19'),
(19, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 16:05:28'),
(20, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 16:29:25'),
(21, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 17:08:13'),
(22, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 17:13:12'),
(23, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 17:13:24'),
(24, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 17:29:01'),
(25, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 19:18:05'),
(26, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 19:25:58'),
(27, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 19:26:05'),
(28, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 19:28:07'),
(29, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 19:28:24'),
(30, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 19:36:36'),
(31, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 19:36:50'),
(32, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 19:38:58'),
(33, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 19:39:07'),
(34, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-09 20:10:50'),
(35, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-09 20:10:58'),
(36, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-10 05:58:19'),
(37, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-10 06:00:04'),
(38, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-10 06:00:16'),
(39, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-10 06:04:41'),
(40, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-10 06:04:50'),
(41, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-10 06:06:53'),
(42, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-10 06:06:59'),
(43, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-13 03:35:36'),
(44, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-13 03:37:36'),
(45, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-13 03:37:46'),
(46, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-13 03:39:40'),
(47, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-13 03:41:22'),
(48, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-13 03:41:42'),
(49, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-13 03:50:45'),
(50, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-13 03:58:17'),
(51, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-18 13:32:26'),
(52, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-18 13:33:49'),
(53, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-18 13:34:25'),
(54, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-18 23:56:27'),
(55, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-19 05:12:09'),
(56, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-19 12:41:05'),
(57, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-19 12:48:24'),
(58, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-19 12:48:35'),
(59, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-19 17:04:28'),
(60, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-19 17:04:38'),
(61, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:02:42'),
(62, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:16:47'),
(63, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:18:22'),
(64, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 13:19:07'),
(65, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:19:39'),
(66, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:20:43'),
(67, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:21:05'),
(68, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 13:21:32'),
(69, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:21:48'),
(70, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 13:22:24'),
(71, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:23:01'),
(72, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 13:26:22'),
(73, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:26:30'),
(74, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 13:39:19'),
(75, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 13:39:26'),
(76, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 14:16:45'),
(77, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 14:17:01'),
(78, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-21 14:17:20'),
(79, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-21 14:26:04'),
(80, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-22 06:14:14'),
(81, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-22 08:07:54'),
(82, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-22 08:08:43'),
(83, 2, 'Account Updated', 'Your account role has been updated by an administrator.', 'info', 0, '2025-08-22 08:13:15'),
(84, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-22 08:15:07'),
(85, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-22 08:15:15'),
(86, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:03:00'),
(87, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:03:40'),
(88, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:03:55'),
(89, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:12:44'),
(90, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:12:56'),
(91, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:14:16'),
(92, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:14:30'),
(93, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:14:55'),
(94, 2, 'Account Updated', 'Your account role has been updated by an administrator.', 'info', 0, '2025-08-23 07:15:19'),
(95, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:18:57'),
(96, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:19:09'),
(97, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:20:19'),
(98, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:20:26'),
(99, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:22:29'),
(100, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:22:36'),
(101, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 07:23:16'),
(102, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 07:23:28'),
(103, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 08:00:22'),
(104, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 08:05:01'),
(105, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-23 08:05:14'),
(106, 2, 'Account Updated', 'Your account role has been updated by an administrator.', 'info', 0, '2025-08-23 08:06:24'),
(107, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-23 08:11:25'),
(108, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 05:50:23'),
(109, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 05:52:43'),
(110, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 05:52:52'),
(111, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 05:53:03'),
(112, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 05:53:29'),
(113, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 05:56:15'),
(114, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 05:56:24'),
(115, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 05:57:15'),
(116, 4, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 05:57:24'),
(117, 4, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 06:00:30'),
(118, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 06:00:40'),
(119, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 06:14:16'),
(120, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 06:16:35'),
(121, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-08-27 06:21:19'),
(122, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-08-27 13:50:51'),
(123, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-08 16:27:11'),
(124, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-08 16:27:57'),
(125, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-08 16:37:48'),
(126, 5, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-08 16:58:03'),
(127, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-08 16:58:11'),
(128, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-08 17:03:00'),
(129, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-08 17:06:35'),
(130, 5, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 00:36:55'),
(131, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 00:39:58'),
(132, 5, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 01:10:39'),
(133, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 01:10:54'),
(134, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 01:13:06'),
(135, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 01:13:21'),
(136, 5, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 01:14:36'),
(137, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 01:14:43'),
(138, 5, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 01:15:31'),
(139, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 01:15:37'),
(140, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 01:17:52'),
(141, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 01:29:09'),
(142, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 01:29:43'),
(143, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 01:29:49'),
(144, 5, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-09 03:18:49'),
(145, 5, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-09 03:19:17'),
(146, 2, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-28 02:27:13'),
(147, 2, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-28 02:27:43'),
(148, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-28 02:28:25'),
(149, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-30 13:58:55'),
(150, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-30 13:59:40'),
(151, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-30 13:59:49'),
(152, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-09-30 14:06:33'),
(153, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-09-30 14:06:54'),
(154, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:13:47'),
(155, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:14:00'),
(156, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:14:39'),
(157, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:16:16'),
(158, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:16:29'),
(159, 3, 'Account Updated', 'Your account role has been updated by an administrator.', 'info', 0, '2025-10-02 11:16:56'),
(160, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:18:34'),
(161, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:18:49'),
(162, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:20:10'),
(163, 6, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:22:58'),
(164, 3, 'New Team Join Request', 'Lebron James wants to join your team!', 'info', 0, '2025-10-02 11:23:56'),
(165, 6, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:24:09'),
(166, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:24:25'),
(167, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:41:02'),
(168, 6, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:41:30'),
(169, 6, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:43:17'),
(170, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:45:04'),
(171, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 11:55:07'),
(172, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 11:55:19'),
(173, 6, 'Request Approved', 'Your request to join Team Black has been approved! Welcome to the team!', 'success', 0, '2025-10-02 12:01:46'),
(174, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:07:00'),
(175, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:07:34'),
(176, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:09:34'),
(177, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:09:53'),
(178, 7, 'Request Approved', 'Your request to join Team Black has been approved! Welcome to the team!', 'success', 0, '2025-10-02 12:10:17'),
(179, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:16:18'),
(180, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:17:32'),
(181, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:18:52'),
(182, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:19:06'),
(183, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:21:33'),
(184, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:23:45'),
(185, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:33:30'),
(186, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:33:44'),
(187, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:57:56'),
(188, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:58:04'),
(189, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:59:23'),
(190, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 12:59:37'),
(191, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 12:59:52'),
(192, 8, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:00:51'),
(193, 8, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 13:02:17'),
(194, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:02:27'),
(195, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 13:05:06'),
(196, 8, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:06:15'),
(197, 7, 'Request Approved', 'Your request to join Team bangan has been approved! Welcome to the team!', 'success', 0, '2025-10-02 13:07:09'),
(198, 8, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 13:08:34'),
(199, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:09:38'),
(200, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 13:10:32'),
(201, 8, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:11:52'),
(202, 8, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 13:15:29'),
(203, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:23:12'),
(204, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 13:32:55'),
(205, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 13:33:02'),
(206, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 14:34:01'),
(207, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 16:58:31'),
(208, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:00:28'),
(209, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:00:37'),
(210, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:01:37'),
(211, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:01:50'),
(212, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:04:49'),
(213, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:05:06'),
(214, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:05:32'),
(215, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:05:41'),
(216, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:06:00'),
(217, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:08:09'),
(218, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:12:54'),
(219, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:13:05'),
(220, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-02 17:22:55'),
(221, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-02 17:24:51'),
(222, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 06:46:20'),
(223, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 06:47:59'),
(224, 11, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 06:55:48'),
(225, 11, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 06:56:15'),
(226, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 06:56:25'),
(227, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 06:56:55'),
(228, 11, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 06:57:06'),
(229, 11, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 06:59:46'),
(230, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 07:00:02'),
(231, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 07:38:56'),
(232, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 07:39:07'),
(233, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 07:40:24'),
(234, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 07:40:30'),
(235, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 07:51:54'),
(236, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 15:37:56'),
(237, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 15:47:36'),
(238, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 15:50:36'),
(239, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 15:52:59'),
(240, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 15:53:10'),
(241, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 15:53:39'),
(242, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 15:53:44'),
(243, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 16:12:53'),
(244, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 16:13:04'),
(245, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 16:23:03'),
(246, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 16:23:46'),
(247, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 16:26:12'),
(248, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 16:26:24'),
(249, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 16:43:16'),
(250, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 16:43:23'),
(251, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 16:44:30'),
(252, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 16:44:38'),
(253, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:27:59'),
(254, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:28:11'),
(255, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:30:15'),
(256, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:30:23'),
(257, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:32:16'),
(258, 11, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:32:38'),
(259, 3, 'New Team Join Request', 'Jeinilyn Unknown wants to join your team!', 'info', 0, '2025-10-03 17:35:39'),
(260, 11, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:35:56'),
(261, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:36:06'),
(262, 11, 'Request Approved', 'Your request to join Ebale Team has been approved! Welcome to the team!', 'success', 0, '2025-10-03 17:36:44'),
(263, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:39:08'),
(264, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:39:17'),
(265, 3, 'New Team Join Request', 'peter gwapo wants to join your team!', 'info', 0, '2025-10-03 17:40:06'),
(266, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:40:23'),
(267, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:40:38'),
(268, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:41:22'),
(269, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:41:34'),
(270, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:49:43'),
(271, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:50:09'),
(272, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-03 17:53:10'),
(273, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-03 17:53:17'),
(274, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 05:27:25'),
(275, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 05:59:24'),
(276, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 05:59:32'),
(277, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 05:59:50'),
(278, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 05:59:56'),
(279, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 06:01:15'),
(280, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 06:01:24'),
(281, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 06:12:56'),
(282, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 06:29:02'),
(283, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 06:31:55'),
(284, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 06:32:07'),
(285, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 06:37:43'),
(286, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 15:58:28'),
(287, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 16:42:05'),
(288, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 16:42:13'),
(289, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 16:43:05'),
(290, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 16:43:16'),
(291, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 16:48:25'),
(292, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 16:48:33'),
(293, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 17:00:39'),
(294, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 17:00:46'),
(295, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 17:16:36'),
(296, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 17:16:43'),
(297, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 17:21:42'),
(298, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 17:22:01'),
(299, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 17:22:40'),
(300, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 17:22:49'),
(301, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 17:24:15'),
(302, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 17:24:25'),
(303, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 17:50:19'),
(304, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 17:50:56'),
(305, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 18:11:44'),
(306, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-04 18:11:51'),
(307, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-04 18:12:07'),
(308, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:07:43'),
(309, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:08:16'),
(310, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:08:28'),
(311, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:09:31'),
(312, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:09:49'),
(313, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:25:22'),
(314, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:25:30'),
(315, 3, 'New Team Join Request', 'peter gwapo wants to join your team!', 'info', 0, '2025-10-05 06:27:03'),
(316, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:27:09'),
(317, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:27:17'),
(318, 7, 'Request Approved', 'Your request to join Team Gwapo has been approved! Welcome to the team!', 'success', 0, '2025-10-05 06:27:36'),
(319, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:27:43'),
(320, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:27:51'),
(321, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:41:31'),
(322, 8, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:42:07'),
(323, 8, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 06:52:34'),
(324, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 06:54:25'),
(325, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:13:23'),
(326, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:20:05'),
(327, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:20:17'),
(328, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:28:13'),
(329, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:28:19'),
(330, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:29:38'),
(331, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:29:46'),
(332, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:31:10'),
(333, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:31:22'),
(334, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:34:03'),
(335, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:34:22'),
(336, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:39:30'),
(337, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:39:40'),
(338, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:40:13'),
(339, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:40:24'),
(340, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 15:43:19'),
(341, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 15:43:45'),
(342, 8, 'New Team Join Request', 'peter gwapo wants to join your team!', 'info', 0, '2025-10-05 15:46:54'),
(343, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:02:40'),
(344, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:02:46'),
(345, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:04:17'),
(346, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:04:28'),
(347, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:10:24'),
(348, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:10:47'),
(349, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:12:36'),
(350, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:12:50'),
(351, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:20:06'),
(352, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:20:13'),
(353, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:23:27'),
(354, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:23:36'),
(355, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:28:12'),
(356, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-05 16:28:24'),
(357, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-05 16:44:39'),
(358, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-08 07:43:42'),
(359, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-08 08:10:30'),
(360, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-08 08:10:35'),
(361, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-08 08:12:02'),
(362, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 02:14:44'),
(363, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 02:16:02'),
(364, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 02:16:11'),
(365, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 02:17:07'),
(366, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 03:40:11'),
(367, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 04:38:46'),
(368, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 04:38:57'),
(369, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 04:39:51'),
(370, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 04:40:08'),
(371, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 04:40:56'),
(372, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 04:41:03'),
(373, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 04:41:33'),
(374, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 04:41:59'),
(375, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 04:44:39'),
(376, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 04:45:03'),
(377, 3, 'New Team Join Request', 'peter gwapo wants to join your team!', 'info', 0, '2025-10-11 04:47:16'),
(378, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 04:47:24'),
(379, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 04:47:38'),
(380, 7, 'Request Approved', 'Your request to join DV has been approved! Welcome to the team!', 'success', 0, '2025-10-11 04:48:34'),
(381, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 05:11:16'),
(382, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 05:11:28'),
(383, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 05:13:57'),
(384, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 05:14:07'),
(385, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 05:40:57'),
(386, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 05:41:10'),
(387, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 05:44:27'),
(388, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 08:12:24'),
(389, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 08:15:59'),
(390, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 08:16:23'),
(391, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 08:52:24'),
(392, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 08:52:37'),
(393, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 09:32:35'),
(394, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 09:40:20'),
(395, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 09:44:44'),
(396, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-11 09:45:09'),
(397, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-11 10:24:26'),
(398, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 13:52:37'),
(399, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 14:02:52'),
(400, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 14:03:35'),
(401, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:07:14'),
(402, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:07:26'),
(403, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:15:41'),
(404, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:17:22'),
(405, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:21:07'),
(406, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:21:15'),
(407, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:22:32'),
(408, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:22:40'),
(409, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:22:56'),
(410, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:23:04'),
(411, 3, 'New Team Join Request', 'peter hahays wants to join your team!', 'info', 0, '2025-10-15 15:24:45'),
(412, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:25:04'),
(413, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:25:15'),
(414, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 15:33:25'),
(415, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-15 15:33:31'),
(416, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-15 16:40:58'),
(417, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-16 02:14:40'),
(418, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-16 02:19:27'),
(419, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-16 02:19:38'),
(420, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-16 02:21:36'),
(421, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-16 02:21:45'),
(422, 1, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-16 02:22:26'),
(423, 7, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-16 02:22:37'),
(424, 3, 'New Team Join Request', 'peter hahays wants to join your team!', 'info', 0, '2025-10-16 02:24:15'),
(425, 7, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-16 02:24:24'),
(426, 3, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-16 02:24:35'),
(427, 7, 'Request Approved', 'Your request to join CEC BLUE DRAGONS has been approved! Welcome to the team!', 'success', 0, '2025-10-16 02:25:32'),
(428, 3, 'Logged Out', 'You have successfully logged out.', 'info', 0, '2025-10-16 02:26:32'),
(429, 1, 'Welcome Back!', 'You have successfully logged in.', 'success', 0, '2025-10-16 02:26:51');

-- --------------------------------------------------------

--
-- Table structure for table `player_stats`
--

CREATE TABLE `player_stats` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `matches_played` int(11) DEFAULT 0,
  `goals` int(11) DEFAULT 0,
  `assists` int(11) DEFAULT 0,
  `yellow_cards` int(11) DEFAULT 0,
  `red_cards` int(11) DEFAULT 0,
  `minutes_played` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_requests`
--

CREATE TABLE `registration_requests` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `sitio` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `team_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `preferred_position` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_requests`
--

INSERT INTO `registration_requests` (`id`, `player_id`, `full_name`, `current_address`, `sitio`, `age`, `birthday`, `team_id`, `league_id`, `preferred_position`, `message`, `document_path`, `status`, `created_at`, `processed_at`, `processed_by`) VALUES
(3, 7, 'Janmark', 'Buhisan', 'Lower Puti', 23, '2002-02-12', 9, 17, 'Center', 'paapila ko please', 'uploads/documents/psa_7_1759410268.png', 'approved', '2025-10-02 13:04:28', '2025-10-02 13:07:09', 8),
(4, 1, NULL, NULL, NULL, NULL, NULL, 9, 17, 'Center', 'zz', NULL, 'pending', '2025-10-02 17:00:08', NULL, NULL),
(5, 11, 'Earl Debalocos', 'Liloan', 'Ungoan', 22, '2002-10-14', 10, 18, 'Point Guard', 'ill do my best', 'uploads/documents/psa_11_1759512939.pdf', 'approved', '2025-10-03 17:35:39', '2025-10-03 17:36:44', 3),
(6, 7, 'Peter', 'Liloan', 'Ungoan', 23, '2001-10-16', 10, 18, 'Center', 'w3', 'uploads/documents/psa_7_1759513206.pdf', 'pending', '2025-10-03 17:40:06', NULL, NULL),
(7, 7, 'Peter', 'Liloan', 'Ungoan', 23, '2001-10-16', 12, 20, 'Center', 'wewe', 'uploads/documents/psa_7_1759645623.png', 'approved', '2025-10-05 06:27:03', '2025-10-05 06:27:36', 3),
(8, 7, 'John', 'Cec', 'Leon Kilat', 24, '2000-10-16', 14, 21, 'Point Guard', 'wewe', 'uploads/documents/psa_7_1759679214.png', 'pending', '2025-10-05 15:46:54', NULL, NULL),
(9, 7, 'KRIS', 'buhisan', 'Lower Puti', 18, '2006-10-15', 15, 23, 'Center', 'WQWEQWE', 'uploads/documents/psa_7_1760158036.pdf', 'approved', '2025-10-11 04:47:16', '2025-10-11 04:48:34', 3),
(10, 7, 'Peter Parker', 'buhisan', 'Lower Puti', 22, '2002-10-22', 15, 23, 'Center', 'let me join', 'uploads/documents/psa_7_1760541885.pdf', 'pending', '2025-10-15 15:24:45', NULL, NULL),
(11, 7, 'MJay calayag', 'buhisan', 'Ungoan', 21, '2003-10-21', 16, 24, 'Center', 'let me join', 'uploads/documents/psa_7_1760581455.pdf', 'approved', '2025-10-16 02:24:15', '2025-10-16 02:25:32', 3);

-- --------------------------------------------------------

--
-- Table structure for table `sports`
--

CREATE TABLE `sports` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `max_players_per_team` int(11) DEFAULT 11,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sports`
--

INSERT INTO `sports` (`id`, `name`, `description`, `max_players_per_team`, `created_at`) VALUES
(1, 'Badminton', 'Badminton Association (Single & Doubles)', 2, '2025-08-09 03:22:05'),
(2, 'Basketball', 'Labangon Basketball', 13, '2025-08-09 03:22:05'),
(3, 'Volleyball', 'Indoor/Outdoor Volleyball', 13, '2025-08-09 03:22:05'),
(4, 'Tennis', 'Singles/Doubles Tennis', 2, '2025-08-09 03:22:05');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `league_id` int(11) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `wins` int(11) DEFAULT 0,
  `losses` int(11) DEFAULT 0,
  `draws` int(11) DEFAULT 0,
  `points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `goals_for` int(11) DEFAULT 0,
  `goals_against` int(11) DEFAULT 0,
  `matches_played` int(11) DEFAULT 0,
  `score_difference` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `league_id`, `owner_id`, `description`, `logo_url`, `wins`, `losses`, `draws`, `points`, `created_at`, `goals_for`, `goals_against`) VALUES
(1, 'Red', 1, 1, 'wqewqe', NULL, 0, 0, 0, 0, '2025-08-09 05:37:38', 0, 0),
(2, 'wqewq', 2, 1, 'qwe', NULL, 0, 0, 0, 0, '2025-08-09 05:38:31', 0, 0),
(4, 'zz', 2, 2, 'wewe', NULL, 0, 0, 0, 0, '2025-08-09 16:05:01', 0, 0),
(5, 'xxx', 4, 2, 'xxx', NULL, 0, 0, 0, 0, '2025-08-13 03:59:07', 0, 0),
(6, 'Team Black', 11, 2, 'champions', NULL, 0, 0, 0, 0, '2025-08-21 14:18:13', 0, 0),
(9, 'Team bangan', 17, 8, 'bulabog', NULL, 0, 0, 0, 0, '2025-10-02 13:01:36', 0, 0),
(10, 'Ebale Team', 18, 3, 'We play for fun', NULL, 0, 0, 0, 0, '2025-10-03 17:31:44', 0, 0),
(11, 'WHITE', 19, 3, 'QWEE', NULL, 0, 0, 0, 0, '2025-10-04 06:02:02', 0, 0),
(12, 'Team Gwapo', 20, 3, '112', NULL, 0, 0, 0, 0, '2025-10-04 16:42:46', 0, 0),
(13, 'Heroics', 21, 3, NULL, NULL, 0, 0, 0, 0, '2025-10-05 06:36:19', 0, 0),
(14, 'Team BLUE', 21, 8, NULL, NULL, 0, 0, 0, 0, '2025-10-05 06:54:52', 0, 0),
(15, 'DV', 23, 3, NULL, NULL, 0, 0, 0, 0, '2025-10-11 04:41:20', 0, 0),
(16, 'CEC BLUE DRAGONS', 24, 3, NULL, NULL, 0, 0, 0, 0, '2025-10-16 02:22:20', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `team_league_registrations`
--

CREATE TABLE `team_league_registrations` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `sitio` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `birthday` date NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `photo_id_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `position` varchar(50) DEFAULT NULL,
  `jersey_number` int(11) DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `player_id`, `position`, `jersey_number`, `joined_at`, `status`) VALUES
(1, 1, 1, 'Captain', NULL, '2025-08-09 05:37:38', 'active'),
(2, 2, 1, 'Captain', NULL, '2025-08-09 05:38:31', 'active'),
(4, 4, 2, 'Captain', NULL, '2025-08-09 16:05:01', 'active'),
(5, 5, 2, 'Captain', NULL, '2025-08-13 03:59:07', 'active'),
(6, 6, 2, 'Captain', NULL, '2025-08-21 14:18:13', 'active'),
(11, 9, 8, 'Captain', NULL, '2025-10-02 13:01:36', 'active'),
(12, 9, 7, 'Center', NULL, '2025-10-02 13:07:09', 'active'),
(15, 11, 3, 'Captain', NULL, '2025-10-04 06:02:02', 'active'),
(16, 12, 3, 'Captain', NULL, '2025-10-04 16:42:46', 'active'),
(17, 12, 7, 'Center', NULL, '2025-10-05 06:27:36', 'active'),
(18, 13, 3, 'Owner', NULL, '2025-10-05 06:36:19', 'active'),
(19, 14, 8, 'Owner', NULL, '2025-10-05 06:54:52', 'active'),
(20, 15, 3, 'Owner', NULL, '2025-10-11 04:41:20', 'active'),
(22, 16, 3, 'Owner', NULL, '2025-10-16 02:22:20', 'active'),
(23, 16, 7, 'Center', NULL, '2025-10-16 02:25:32', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `team_registration_requests`
--

CREATE TABLE `team_registration_requests` (
  `id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `team_owner_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `request_message` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_registration_requests`
--

INSERT INTO `team_registration_requests` (`id`, `league_id`, `team_name`, `team_owner_id`, `status`, `request_message`, `admin_notes`, `created_at`, `reviewed_at`, `reviewed_by`) VALUES
(1, 21, 'Falcons', 3, 'rejected', 'im james', '', '2025-10-04 17:23:50', '2025-10-04 18:11:38', 1),
(2, 21, 'Heroics', 3, 'approved', 'we play for fun', '', '2025-10-05 06:09:19', '2025-10-05 06:36:19', 1),
(3, 21, 'Team BLUE', 8, 'approved', 'This team is awesome!', '', '2025-10-05 06:51:50', '2025-10-05 06:54:52', 1),
(4, 23, 'DV', 3, 'approved', 'APILA KO', '', '2025-10-11 04:40:43', '2025-10-11 04:41:20', 1),
(5, 24, 'CEC BLUE DRAGONS', 3, 'approved', 'play for fun', '', '2025-10-16 02:21:06', '2025-10-16 02:22:20', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `role` enum('player','team_owner','admin') DEFAULT 'player',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `address`, `role`, `created_at`, `updated_at`, `status`) VALUES
(1, 'admin', 'admin@sportsleague.com', '$2y$10$QRMEydO4LLStL5/amQ1sTexmcZMvj1.sGxBa6CuKdCiJil7buEMyG', 'System', 'Administrator', '', 'Buhisan', 'admin', '2025-08-09 03:22:05', '2025-10-16 02:26:51', 'active'),
(2, 'vanz', 'vanzy@gmail.com', '$2y$10$pCBh51c8u.ZTeXMKYMfpnexqFn2jBcGpsUe/j/Mldeos1WVMDgW/O', 'John', 'Mark', '112212', NULL, 'team_owner', '2025-08-09 15:54:36', '2025-09-28 02:27:43', 'active'),
(3, 'james', 'james@gmail.com', '$2y$10$gRok6CiY8I/O8hEzJX1aHueAcV6zRgVLFBIahBJkI14IS/spPiidy', 'James', 'Lover', '09452560280', NULL, 'team_owner', '2025-08-22 08:12:15', '2025-10-16 02:26:32', 'active'),
(4, 'leemar', 'leemar@gmail.com', '$2y$10$xWyt/kdcivlh1QtuCH5.L.X6rOq.Mept2M/OHEG27KdH72BpJrzia', 'Leemar', 'Baril', '09452560280', NULL, 'player', '2025-08-27 05:57:13', '2025-08-27 06:00:30', 'active'),
(5, 'kevs', 'kevs@gmail.com', '$2y$10$Gbht9fq1jYVkjJd3l9N14eN6AbwxNeYbULcKxzowT.MHXzL9kNRaO', 'Kevin', 'John', '093248699580', NULL, 'player', '2025-09-08 16:37:31', '2025-09-09 03:19:17', 'active'),
(6, 'lebron', 'lebronjames@gmail.com', '$2y$10$NErWJTkrxpulB5gV2jBaC.S3GO/A.E8EAtaBd/Yqr0epyvP2pTXBC', 'Lebron', 'James', '09452560280', NULL, 'player', '2025-10-02 11:22:36', '2025-10-02 11:43:17', 'active'),
(7, 'peter', 'peter@gmail.com', '$2y$10$R5cWZwc5TA2d3Kqnl5uIru.d6kcVBYjA.L.OY0U.EzeRFW7ZHkR06', 'peter', 'hahays', '565565656', NULL, 'player', '2025-10-02 11:44:42', '2025-10-16 02:24:24', 'active'),
(8, 'mjay', 'mjay@gmail.com', '$2y$10$L50HHB0kEOPT24P4IQ8DSeuv4H0WnQ0Mlac3PEigJ4t.bGQtI5NcO', 'Mjay', 'Calayag', '123123', NULL, 'team_owner', '2025-10-02 13:00:38', '2025-10-05 06:52:34', 'active'),
(10, 'roly', 'roly@gmail.com', '$2y$10$I15eGhwampoK01D4WcNRkOURaP2Jde10NtzZTqRUDuxufGfnvwVNC', 'roly', 'gastanes', '000000', NULL, 'player', '2025-10-02 17:25:49', '2025-10-02 17:25:49', 'active'),
(11, 'Jeinilyn', 'jeinilyn@gmail.com', '$2y$10$2sU5qiQIn91.dlmBOjdNYOR0gxEjxtPbbn22HWfF1CYtECcZHdtrO', 'Jeinilyn', 'Unknown', '0912312323', NULL, 'player', '2025-10-03 06:55:36', '2025-10-03 17:35:56', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `facilities` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `name`, `address`, `capacity`, `facilities`, `created_at`) VALUES
(1, 'Central Sports Complex', '123 Sports Ave, City Center', 5000, NULL, '2025-08-09 03:22:05'),
(2, 'Community Field A', '456 Park St, Downtown', 1000, NULL, '2025-08-09 03:22:05'),
(3, 'Indoor Arena', '789 Arena Blvd, Sports District', 3000, NULL, '2025-08-09 03:22:05'),
(4, 'zzz', 'asdasdasd', 500, 'asd', '2025-08-09 05:45:24'),
(5, 'Cebu EASTERN', 'LEON KILAT STREET', 1000, 'PAY PARKING', '2025-10-02 11:18:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leagues`
--
ALTER TABLE `leagues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sport_id` (`sport_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `league_id` (`league_id`),
  ADD KEY `home_team_id` (`home_team_id`),
  ADD KEY `away_team_id` (`away_team_id`),
  ADD KEY `venue_id` (`venue_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `player_stats`
--
ALTER TABLE `player_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_player_league` (`player_id`,`league_id`),
  ADD KEY `league_id` (`league_id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `league_id` (`league_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `sports`
--
ALTER TABLE `sports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `league_id` (`league_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `team_league_registrations`
--
ALTER TABLE `team_league_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_league` (`team_id`,`league_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_team_id` (`team_id`),
  ADD KEY `idx_league_id` (`league_id`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_player` (`team_id`,`player_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `team_registration_requests`
--
ALTER TABLE `team_registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `league_id` (`league_id`),
  ADD KEY `team_owner_id` (`team_owner_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leagues`
--
ALTER TABLE `leagues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=430;

--
-- AUTO_INCREMENT for table `player_stats`
--
ALTER TABLE `player_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sports`
--
ALTER TABLE `sports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `team_league_registrations`
--
ALTER TABLE `team_league_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `team_registration_requests`
--
ALTER TABLE `team_registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leagues`
--
ALTER TABLE `leagues`
  ADD CONSTRAINT `leagues_ibfk_1` FOREIGN KEY (`sport_id`) REFERENCES `sports` (`id`),
  ADD CONSTRAINT `leagues_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`home_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`away_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_4` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `player_stats`
--
ALTER TABLE `player_stats`
  ADD CONSTRAINT `player_stats_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `player_stats_ibfk_2` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `player_stats_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD CONSTRAINT `registration_requests_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `registration_requests_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `registration_requests_ibfk_3` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `registration_requests_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `teams_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `team_league_registrations`
--
ALTER TABLE `team_league_registrations`
  ADD CONSTRAINT `team_league_registrations_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_league_registrations_ibfk_2` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_league_registrations_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_league_registrations_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `team_registration_requests`
--
ALTER TABLE `team_registration_requests`
  ADD CONSTRAINT `team_registration_requests_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_registration_requests_ibfk_2` FOREIGN KEY (`team_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_registration_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
