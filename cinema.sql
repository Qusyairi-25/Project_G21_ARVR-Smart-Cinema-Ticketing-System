-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2026 at 03:32 PM
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
-- Database: `cinema`
--

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `badge_id` varchar(50) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `badge_description` text DEFAULT NULL,
  `badge_icon` varchar(50) DEFAULT NULL,
  `requirement_type` varchar(50) NOT NULL COMMENT 'reviews, bookings, wishlist, spending, genre',
  `requirement_value` int(11) NOT NULL,
  `discount_type` varchar(20) NOT NULL COMMENT 'percentage or fixed',
  `discount_value` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `allows_early_access` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `badge_id`, `badge_name`, `badge_description`, `badge_icon`, `requirement_type`, `requirement_value`, `discount_type`, `discount_value`, `is_active`, `allows_early_access`, `created_at`) VALUES
(1, 'first_review', '🎬 First Steps', 'Wrote your first movie review', 'fa-star', 'reviews', 1, 'percentage', 10.00, 1, 0, '2026-05-10 17:15:58'),
(2, 'review_master', '📝 Review Master', 'Wrote 10 movie reviews', 'fa-pen-fancy', 'reviews', 10, 'percentage', 20.00, 1, 0, '2026-05-10 17:15:58'),
(3, 'critic', '🎭 The Critic', 'Wrote 25 movie reviews', 'fa-trophy', 'reviews', 25, 'fixed', 15.00, 1, 0, '2026-05-10 17:15:58'),
(4, 'movie_addict', '🍿 Movie Addict', 'Booked 20 movie tickets', 'fa-film', 'bookings', 20, 'percentage', 25.00, 1, 0, '2026-05-10 17:15:58'),
(5, 'cinema_king', '👑 Cinema King', 'Booked 50 movie tickets', 'fa-crown', 'bookings', 50, 'fixed', 50.00, 1, 0, '2026-05-10 17:15:58'),
(6, 'horror_addict', '🔪 Horror Addict', 'Watched 5 horror movies', 'fa-skull', 'genre_horror', 5, 'percentage', 15.00, 1, 0, '2026-05-10 17:15:58'),
(7, 'action_junkie', '💥 Action Junkie', 'Watched 5 action movies', 'fa-fist-raised', 'genre_action', 5, 'percentage', 15.00, 1, 0, '2026-05-10 17:15:58'),
(8, 'romance_guru', '💕 Romance Guru', 'Watched 5 romance movies', 'fa-heart', 'genre_romance', 5, 'percentage', 15.00, 1, 0, '2026-05-10 17:15:58'),
(9, 'wishlist_collector', '📋 Wishlist Collector', 'Added 10 movies to wishlist', 'fa-list', 'wishlist', 10, 'percentage', 10.00, 1, 0, '2026-05-10 17:15:58'),
(10, 'big_spender', '💰 Big Spender', 'Spent over RM200 on tickets', 'fa-money-bill-wave', 'spending', 200, 'percentage', 30.00, 1, 0, '2026-05-10 17:15:58'),
(11, 'early_access_vip', '🎟️ VIP Early Access', 'Book movies before official release date', 'fa-ticket', 'bookings', 10, 'percentage', 15.00, 1, 1, '2026-05-27 23:37:31');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `show_time` varchar(20) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `booking_date` datetime NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `booking_group_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `movie_id`, `show_time`, `seat_number`, `total_price`, `booking_date`, `status`, `payment_method`, `payment_date`, `booking_group_id`) VALUES
(9, 25, 12, '4:00 PM', 'A8', 28.00, '2026-05-09 23:58:41', 'pending', NULL, NULL, NULL),
(10, 25, 12, '4:00 PM', 'A7', 28.00, '2026-05-10 00:01:01', 'pending', NULL, NULL, NULL),
(11, 25, 12, '4:00 PM', 'A8', 28.00, '2026-05-10 00:01:01', 'pending', NULL, NULL, NULL),
(12, 25, 12, '4:00 PM', 'A7', 28.00, '2026-05-10 00:01:42', 'pending', NULL, NULL, NULL),
(13, 25, 12, '4:00 PM', 'A8', 28.00, '2026-05-10 00:01:42', 'cancelled', 'ewallet', '2026-05-10 00:01:50', NULL),
(14, 25, 12, '4:00 PM', 'D6', 42.00, '2026-05-10 00:13:46', 'cancelled', 'credit_card', '2026-05-10 00:19:20', NULL),
(15, 25, 12, '4:00 PM', 'D7', 42.00, '2026-05-10 00:13:46', 'cancelled', 'credit_card', '2026-05-10 00:19:20', NULL),
(16, 25, 12, '4:00 PM', 'D8', 42.00, '2026-05-10 00:13:46', 'confirmed', 'credit_card', '2026-05-10 00:19:20', NULL),
(17, 25, 12, '4:00 PM', 'D8', 14.00, '2026-05-10 00:29:13', 'pending', NULL, NULL, NULL),
(18, 25, 12, '4:00 PM', 'B1', 28.00, '2026-05-10 00:49:07', 'confirmed', 'online_banking', '2026-05-10 00:49:12', NULL),
(19, 25, 12, '4:00 PM', 'B2', 28.00, '2026-05-10 00:49:07', 'confirmed', 'online_banking', '2026-05-10 00:49:12', NULL),
(20, 25, 12, '4:00 PM', 'E4', 14.00, '2026-05-10 00:50:21', 'confirmed', 'debit_card', '2026-05-10 00:50:23', NULL),
(21, 22, 12, '4:00 PM', 'F6', 14.00, '2026-05-10 11:03:55', 'pending', NULL, NULL, NULL),
(22, 22, 20, '4:00 PM', 'A6', 28.00, '2026-05-10 11:48:50', 'pending', NULL, NULL, NULL),
(23, 22, 20, '4:00 PM', 'A7', 28.00, '2026-05-10 11:48:50', 'pending', NULL, NULL, NULL),
(24, 22, 20, '4:00 PM', 'B1', 28.00, '2026-05-10 11:50:27', 'pending', NULL, NULL, NULL),
(25, 22, 20, '4:00 PM', 'B2', 28.00, '2026-05-10 11:50:27', 'pending', NULL, NULL, NULL),
(26, 22, 20, '4:00 PM', 'D9', 21.00, '2026-05-10 12:01:34', 'confirmed', 'credit_card', '2026-05-10 12:01:57', NULL),
(27, 22, 20, '4:00 PM', 'D10', 21.00, '2026-05-10 12:01:34', 'confirmed', 'credit_card', '2026-05-10 12:01:57', NULL),
(28, 22, 20, '4:00 PM', 'F3', 10.50, '2026-05-10 12:38:41', 'confirmed', 'credit_card', '2026-05-10 12:39:02', NULL),
(29, 22, 6, '4:00 PM', 'F4', 33.75, '2026-05-10 12:53:31', 'confirmed', 'ewallet', '2026-05-10 12:53:43', NULL),
(30, 22, 6, '4:00 PM', 'F5', 33.75, '2026-05-10 12:53:31', 'confirmed', 'ewallet', '2026-05-10 12:53:43', NULL),
(31, 22, 6, '4:00 PM', 'F6', 33.75, '2026-05-10 12:53:31', 'confirmed', 'ewallet', '2026-05-10 12:53:43', NULL),
(32, 25, 20, '4:00 PM', 'F6', 14.00, '2026-05-10 18:09:20', 'pending', NULL, NULL, NULL),
(33, 25, 20, '4:00 PM', 'C5', 14.00, '2026-05-11 12:34:30', 'confirmed', 'credit_card', '2026-05-11 12:34:38', NULL),
(34, 25, 39, '4:00 PM', 'D1', 26.00, '2026-05-14 06:21:50', 'pending', NULL, NULL, NULL),
(35, 25, 39, '10:00 AM', 'F1', 26.00, '2026-05-14 06:32:36', 'pending', NULL, NULL, NULL),
(36, 25, 39, '10:00 AM', 'A1', 26.00, '2026-05-14 06:44:09', 'confirmed', 'online_banking', '2026-05-14 12:44:40', NULL),
(37, 25, 39, '10:00 AM', 'A2', 26.00, '2026-05-14 06:44:09', 'confirmed', 'online_banking', '2026-05-14 12:44:40', NULL),
(38, 25, 39, '10:00 AM', 'A3', 26.00, '2026-05-14 06:44:09', 'confirmed', 'online_banking', '2026-05-14 12:44:40', NULL),
(39, 17, 21, '10:30 PM', 'B3', 13.00, '2026-05-14 15:43:11', 'confirmed', 'online_banking', '2026-05-14 21:43:36', NULL),
(40, 17, 38, '10:00 AM', 'D15', 14.00, '2026-05-14 15:45:48', 'confirmed', 'ewallet', '2026-05-14 21:45:55', NULL),
(41, 25, 16, '7:30 PM', 'F2', 14.00, '2026-05-14 15:46:36', 'confirmed', 'debit_card', '2026-05-14 21:46:41', NULL),
(42, 25, 51, '4:00 PM', 'E2', 21.60, '2026-05-27 22:37:46', 'confirmed', 'ewallet', '2026-05-27 22:38:01', NULL),
(43, 25, 51, '4:00 PM', 'E3', 21.60, '2026-05-27 22:37:46', 'confirmed', 'ewallet', '2026-05-27 22:38:01', NULL),
(44, 17, 42, '11.00 PM', 'A4', 19.00, '2026-06-01 20:34:10', 'confirmed', 'credit_card', '2026-06-01 20:34:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_message` text NOT NULL,
  `bot_reply` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_codes`
--

CREATE TABLE `discount_codes` (
  `code_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `discount_percent` int(11) NOT NULL DEFAULT 15,
  `status` enum('active','used','expired') DEFAULT 'active',
  `generated_for` int(11) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount_codes`
--

INSERT INTO `discount_codes` (`code_id`, `code`, `discount_percent`, `status`, `generated_for`, `generated_by`, `generated_at`, `used_at`, `used_by`, `expiry_date`) VALUES
(1, 'STAFFJENNIE25', 25, 'used', 22, NULL, '2026-05-10 04:00:37', '2026-05-10 04:53:43', 22, '2026-06-09'),
(2, 'STAFFANY20', 20, 'active', NULL, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-05-25'),
(3, 'STAFFJENNIE15', 15, 'active', 22, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-06-24'),
(4, 'STAFFSABRINA', 10, 'active', 21, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-05-17'),
(5, 'STAFFEXPIRED', 30, 'expired', 22, NULL, '2026-03-11 04:00:37', NULL, NULL, '2026-04-10'),
(6, 'STAFFUSED', 15, 'used', 22, NULL, '2026-04-30 04:00:37', '2026-05-05 04:00:37', 22, '2026-05-30'),
(7, 'STAFFVIP30', 30, 'active', NULL, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-07-09'),
(8, 'STAFFLIA10', 10, 'active', 19, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-05-24'),
(9, 'STAFFMIN5', 5, 'active', NULL, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-05-20'),
(10, 'STAFFJENNIE50', 50, 'active', 22, NULL, '2026-05-10 04:00:37', NULL, NULL, '2026-05-13');

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `movie_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `rating` varchar(10) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `price` decimal(6,2) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `coming_soon_date` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`movie_id`, `title`, `description`, `genre`, `duration`, `rating`, `release_date`, `poster`, `status`, `price`, `published_at`, `coming_soon_date`, `updated_by`) VALUES
(6, 'Interstellar', 'A team of explorers travel through a wormhole in space.', 'Sci-Fi', 169, '8.7', '2014-11-07', 'https://image.tmdb.org/t/p/w500/qNBAXBIQlnOThrVvA6mA2B5ggV6.jpg', 'published', 15.00, NULL, NULL, NULL),
(7, 'Shang-Chi and the Legend of the Ten Rings', 'Shang-Chi must confront the past he thought he left behind when he is drawn into the web of the mysterious Ten Rings organization.', 'Action', 132, '8.8', '2021-09-03', 'https://image.tmdb.org/t/p/w500/1BIoJGKbXjdFDAqUEiA2VHqkK1Z.jpg', 'published', 14.00, NULL, NULL, 17),
(10, 'John Wick 4', 'John Wick uncovers a path to defeating The High Table.', 'Action', 169, '8.2', '2023-03-24', 'https://image.tmdb.org/t/p/w500/vZloFAK7NmvMGKE7VkF5UHaz0I.jpg', 'published', 15.00, NULL, NULL, NULL),
(11, 'Oppenheimer', 'The story of J. Robert Oppenheimer and the atomic bomb.', 'Drama', 180, '8.9', '2023-07-21', 'https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', 'published', 16.00, NULL, NULL, NULL),
(12, 'Barbie', 'Barbie and Ken go on a journey of self-discovery.', 'Comedy', 114, '7.6', '2023-07-21', 'https://image.tmdb.org/t/p/w500/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg', 'published', 14.00, NULL, NULL, NULL),
(16, 'The Matrix', 'A computer hacker learns from mysterious rebels about the true nature of his reality and his role in the war against its controllers.', 'Sci-Fi', 136, '8.7', '1999-03-31', 'https://image.tmdb.org/t/p/w1280/aOIuZAjPaRIE6CMzbazvcHuHXDc.jpg', 'published', 14.00, NULL, NULL, NULL),
(20, 'A Quiet Place', 'A family struggles for survival in a world where most humans have been killed by blind but noise-sensitive creatures.', 'Horror', 90, '7.5', '2018-04-06', 'https://image.tmdb.org/t/p/w500/nAU74GmpUk7t5iklEp3bufwDq4n.jpg', 'published', 14.00, NULL, NULL, NULL),
(21, 'The Conjuring', 'Paranormal investigators Ed and Lorraine Warren work to help a family terrorized by a dark presence in their farmhouse.', 'Horror', 112, '7.5', '2013-07-19', 'https://image.tmdb.org/t/p/w1280/wVYREutTvI2tmxr6ujrHT704wGF.jpg', 'published', 13.00, NULL, NULL, NULL),
(23, 'Top Gun: Maverick', 'After more than thirty years of service as a top naval aviator, Pete Mitchell is where he belongs, pushing the envelope as a courageous test pilot.', 'Action', 130, '8.4', '2022-05-27', 'https://image.tmdb.org/t/p/w500/62HCnUTziyWcpDaBO2i1DX17ljH.jpg', 'published', 15.00, NULL, NULL, NULL),
(35, 'Ready or Not 2: Here I Come', 'Moments after surviving an all-out attack from the Le Domas family, Grace discovers she’s reached the next level of the nightmarish game — and this time with her estranged sister Faith at her side. Grace has one chance to survive, keep her sister alive, and claim the High Seat of the Council that controls the world. Four rival families are hunting her for the throne, and whoever wins rules it all.', 'Thriller', 162, '8.3', '2026-07-14', 'https://image.tmdb.org/t/p/w1280/oR1oiQ3YnpWjRE42I0Rb8LBd8Dg.jpg', 'coming_soon', 20.00, NULL, NULL, NULL),
(37, 'Lee Cronin\'s The Mummy', 'A reporter\'s young daughter mysteriously disappears in the desert. Eight years later, her sudden return shatters the family, and what should be a joyful reunion becomes a living nightmare.', 'Horror', 133, '6.7', '2026-01-01', 'https://image.tmdb.org/t/p/w1280/3ibnzrfbPIFGwmUW3BLuZV6TzLD.jpg', 'published', 23.00, NULL, NULL, NULL),
(38, '10 Things I Hate About You', 'The spirited daughter of a strict father declares she can date only after her older, antisocial sister does.', 'Romance', 97, '7.8', '1999-03-31', 'https://image.tmdb.org/t/p/w1280/ujERk3aKABXU3NDXOAxEQYTHe9A.jpg', 'published', 14.00, NULL, NULL, NULL),
(39, 'Titanic', 'A seventeen-year-old aristocrat falls in love with a kind but poor artist aboard the luxurious, ill-fated R.M.S. Titanic.', 'Romance', 194, '8.7', '1997-12-19', 'https://image.tmdb.org/t/p/w500/9xjZS2rlVxm8SFx8kPC3aIGCOYQ.jpg', 'published', 26.00, NULL, NULL, NULL),
(40, 'Avatar: Fire and Ash', 'Jake Sully, Neytiri, and their family face a new threat from the Ash People, led by Varang, while the human military prepares another offensive.', 'Fantasy', NULL, NULL, '2025-12-19', 'https://image.tmdb.org/t/p/w1280/aabwWZWx6z1aYP4PX2ADvbDKktd.jpg', 'published', 21.00, NULL, NULL, NULL),
(41, 'Wicked', 'The story of Elphaba, the future Wicked Witch of the West, and her friendship with Glinda the Good, which is tested as they take different paths.', 'Fantasy', 160, '8.3', '2024-11-22', 'http://image.tmdb.org/t/p/w1280/xDGbZ0JJ3mYaGKy4Nzd9Kph6M9L.jpg', 'published', 25.00, NULL, NULL, NULL),
(42, 'The Housemaid', 'A struggling woman, Millie, gets a job as a housemaid for a wealthy couple, only to discover dangerous secrets hidden beneath their opulent surface.', 'Thriller', 131, '7.2', '2025-12-25', 'https://image.tmdb.org/t/p/w1280/cWsBscZzwu5brg9YjNkGewRUvJX.jpg', 'published', 19.00, NULL, NULL, NULL),
(43, 'Toy Story 5', 'Woody, Buzz, Jessie and the rest of the gang\'s jobs are challenged when they\'re introduced to electronics, a new threat to playtime.', 'Comedy', 90, NULL, '2026-06-30', 'https://m.media-amazon.com/images/M/MV5BZTI1YTBiNmEtYWUxZi00YzFkLWIzNjMtMmZjMmY2NzM0ZWMzXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg', 'coming_soon', 23.00, '2026-06-22 23:11:23', '2026-06-22 23:11:23', NULL),
(44, 'Your Name. ', 'High schoolers Mitsuha and Taki are complete strangers living separate lives. But one night, they suddenly switch places. Mitsuha wakes up in Taki’s body, and he in hers. This bizarre occurrence continues to happen randomly, and the two must adjust their lives around each other.', 'Drama', 106, '8.5', '2016-08-26', 'https://image.tmdb.org/t/p/w1280/q719jXXEzOoYaps6babgKnONONX.jpg', 'published', 17.00, NULL, NULL, NULL),
(45, 'Shelter', 'Shelter is a virtual reality documentary that offers an intimate look at the war in Ukraine and the shared humanity of people living through it.', 'Action', 116, '7.5', '2025-03-07', 'https://image.tmdb.org/t/p/w1280/buPFnHZ3xQy6vZEHxbHgL1Pc6CR.jpg', 'published', 18.00, '2026-05-27 23:11:06', '2026-05-27 23:11:00', 17),
(46, 'Jurassic World Rebirth', 'Five years after Jurassic World Dominion, a covert team travels to a dangerous island to secure dinosaur genetic material for a secret mission.', 'Action', 134, '6.4', '2025-07-02', 'https://image.tmdb.org/t/p/w500/q0fGCmjLu42MPlSO9OYWpI5w86I.jpg', 'published', 25.00, NULL, NULL, NULL),
(47, 'Inception', 'A skilled thief enters the dreams of others to steal secrets, but his latest mission involves planting an idea instead.', 'Action', 148, '8.8', '2010-07-16', 'https://image.tmdb.org/t/p/w500/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg', 'published', 20.00, NULL, NULL, NULL),
(48, 'Sniper: No Nation', 'An elite sniper is sent on a dangerous mission across hostile territory while uncovering a deadly conspiracy.', 'Action', 95, '6.7', '2025-01-01', 'https://image.tmdb.org/t/p/w1280/tUmARo0TZEK1EaSuS6dU35FhDyU.jpg', 'published', 19.00, NULL, NULL, NULL),
(49, 'F1', 'A Formula One driver returns to the racing world to mentor a young talent while chasing glory on the track.', 'Action', 155, '8.1', '2025-06-27', 'https://image.tmdb.org/t/p/w1280/vqBmyAj0Xm9LnS1xe1MSlMAJyHq.jpg', 'published', 26.00, NULL, NULL, NULL),
(50, 'The Mortuary Assistant', 'A young mortuary science graduate begins a night shift at a funeral home where terrifying supernatural events begin to unfold.', 'Horror', 91, '4.0', '2026-03-27', 'https://image.tmdb.org/t/p/w1280/72AoFPC5TY4DfJwXXS9rPwPeReD.jpg', 'published', 20.00, NULL, NULL, NULL),
(51, 'Weapons', 'When several children mysteriously disappear on the same night, an entire community becomes haunted by fear and suspicion.', 'Horror', 129, '7.3', '2025-08-08', 'https://image.tmdb.org/t/p/w1280/cpf7vsRZ0MYRQcnLWteD5jK9ymT.jpg', 'published', 24.00, NULL, NULL, NULL),
(52, 'IT', 'In the town of Derry, a group of kids face a terrifying evil clown that feeds on fear.', 'Horror', 135, '7.3', '2017-09-08', 'https://image.tmdb.org/t/p/w500/9E2y5Q7WlCVNEhP5GiVTjhEhx1o.jpg', 'published', 18.00, NULL, NULL, NULL),
(53, 'The Conjuring: Last Rites', 'Paranormal investigators Ed and Lorraine Warren return for one final terrifying case involving dark supernatural forces.', 'Horror', 118, '7.0', '2025-09-05', 'https://image.tmdb.org/t/p/w1280/byWgphT74ClOVa8EOGzYDkl8DVL.jpg', 'published', 23.00, NULL, NULL, NULL),
(54, 'The Nun II', 'Sister Irene once again encounters the demonic force Valak in a chilling new confrontation.', 'Horror', 110, '5.6', '2023-09-08', 'https://image.tmdb.org/t/p/w500/5gzzkR7y3hnY8AD1wXjCnVlHba5.jpg', 'published', 19.00, NULL, NULL, 17),
(55, 'Zootopia 2', 'After cracking the biggest case in Zootopia\'s history, rookie cops Judy Hopps and Nick Wilde find themselves on the twisting trail of a great mystery when Gary De\'Snake arrives and turns the animal metropolis upside down. To crack the case, Judy and Nick must go undercover to unexpected new parts of town, where their growing partnership is tested like never before.', 'Comedy', 108, '7.7', '2025-11-26', 'https://image.tmdb.org/t/p/w1280/oJ7g2CifqpStmoYQyaLQgEU32qO.jpg', 'published', 15.00, '2026-06-06 23:30:23', NULL, 17),
(56, 'Ratatouille', 'Remy, a rat, possesses a palate far more refined than that of his fellow comrades. He dreams of becoming a chef, one who creates rather than scavenges. When fate deposits him in the sewers beneath one of Paris’s most famous restaurants, he finds himself ideally placed to fulfill his dream. Forming an unusual alliance with a hapless young kitchen worker, Remy begins a daring culinary double life. As Remy pursues his vision, he must navigate the suspicions of the calculating Head Chef Skinner, the disapproval of Remy’s own colony, and the foreboding presence of renowned food critic Anton Ego, who strikes fear in the hearts of chefs all throughout France.', 'Comedy', 110, '8.1', '2007-06-28', 'https://image.tmdb.org/t/p/w1280/t3vaWRPSf6WjDSamIkKDs1iQWna.jpg', 'published', 7.50, '2026-06-06 23:37:34', NULL, 17),
(57, 'Obsession', 'After breaking the mysterious \"One Wish Willow\" to win his crush\'s heart, a hopeless romantic finds himself getting exactly what he asked for but soon discovers that some desires come at a dark, sinister price.', 'Thriller', 108, '8.5', '2026-05-15', 'https://image.tmdb.org/t/p/w1280/6X4qFYBsG3bpWDG2XIKqr04kFJa.jpg', 'published', 20.00, '2026-06-06 23:46:22', NULL, 17),
(58, 'Send Help', 'Two colleagues become stranded on a deserted island, the only survivors of a plane crash. On the island, they must overcome past grievances and work together to survive, but ultimately, it\'s a battle of wills and wits to make it out alive.', 'Thriller', 113, '7.8', '2026-01-30', 'https://image.tmdb.org/t/p/w1280/zbJWVHOtj3ljBzWgL1P8pxP03Up.jpg', 'published', 16.99, '2026-06-06 23:48:21', NULL, 17),
(59, 'The Devil Wears Prada 2', 'Andy Sachs returns to Runway as Miranda Priestly navigates a new media landscape and Runway\'s position within. The duo reconnect with former assistant Emily Charlton, now the head of a luxury brand that possesses funding which could ensure Runway\'s survival.', 'Comedy', 119, '6.7', '2026-05-01', 'https://image.tmdb.org/t/p/w1280/xTI42pmsP5EDnvsNJPEDubwWBQO.jpg', 'published', 19.00, '2026-06-06 23:50:26', NULL, 17),
(60, 'The Sheep Detectives', 'George Hardy is a shepherd who reads detective novels to his beloved sheep every night, assuming they can\'t possibly understand. But when a mysterious incident disrupts life on the farm, the sheep realize they must become the detectives. As they follow the clues and investigate human suspects, they prove that even sheep can be brilliant crime-solvers.', 'Comedy', 109, '8.5', '2026-05-08', 'https://image.tmdb.org/t/p/w1280/hTirV44jiLh6NqdiB6jtxPsDIoG.jpg', 'published', 21.00, '2026-06-06 23:52:53', NULL, 17),
(61, 'White Chicks', 'Two FBI agent brothers, Marcus and Kevin Copeland, accidentally foil a drug bust. To avoid being fired they accept a mission escorting a pair of socialites to the Hamptons--but when the girls are disfigured in a car accident, they refuse to go. Left without options, Marcus and Kevin decide to pose as the sisters, transforming themselves from black men into rich European-American women.', 'Comedy', 109, '8.8', '2004-06-23', 'https://image.tmdb.org/t/p/w1280/aHTUpo45qy9QYIOnVITGGqLoVcA.jpg', 'published', 12.00, '2026-06-06 23:55:29', NULL, 17),
(62, 'You Are the Apple of My Eye', 'A group of close friends who attend a private school all have a debilitating crush on the sunny star pupil, Sun-ah. The only member of the group who claims not to is Jin-woo, but he ends up loving her as well.', 'Romance', 101, '7.8', '2024-02-21', 'https://image.tmdb.org/t/p/w1280/bBnyDM1gWBLd91K245iVKH19t42.jpg', 'published', 13.00, '2026-06-07 00:01:02', NULL, 17),
(63, 'Anyone But You', 'After an amazing first date, Bea and Ben’s fiery attraction turns ice cold — until they find themselves unexpectedly reunited at a destination wedding in Australia. So they do what any two mature adults would do: pretend to be a couple.', 'Romance', 103, '6.7', '2023-12-22', 'https://image.tmdb.org/t/p/w1280/yRt7MGBElkLQOYRvLTT1b3B1rcp.jpg', 'published', 13.98, '2026-06-07 00:01:59', NULL, 17),
(64, 'The Idea of You', '40-year-old single mom Solène begins an unexpected romance with 24-year-old Hayes Campbell, the lead singer of August Moon, the hottest boy band on the planet. As they begin a whirlwind romance, it isn\'t long before Hayes\' superstar status poses unavoidable challenges to their relationship, and Solène soon discovers that life in the glare of his spotlight might be more than she bargained for.', 'Romance', 116, '7.3', '2024-05-02', 'https://image.tmdb.org/t/p/w1280/Y5P4Q3q8nrruZ9aD3wXeJS2Plg.jpg', 'published', 16.00, '2026-06-07 00:02:54', NULL, 17),
(65, 'The Kissing Booth', 'When teenager Elle\'s first kiss leads to a forbidden romance with the hottest boy in high school, she risks her relationship with her best friend.', 'Romance', 105, '7.0', '2018-05-11', 'https://image.tmdb.org/t/p/w1280/vcQNnnXgKLacoYF4LNWgkNiDXPd.jpg', 'published', 10.99, '2026-06-07 00:04:13', NULL, 17),
(66, 'A Cinderella Story', 'Routinely exploited by her wicked stepmother, the downtrodden Samantha Montgomery is excited about the prospect of meeting her Internet beau at the school\'s Halloween dance.', 'Romance', 95, '6.2', '2004-07-16', 'https://image.tmdb.org/t/p/w1280/ukwP7gDPWxj1R1dW5iN3mnxkL3D.jpg', 'published', 9.00, '2026-06-07 00:06:26', NULL, 17),
(67, 'Venom: The Last Dance', 'Eddie and Venom are on the run. Hunted by both of their worlds and with the net closing in, the duo are forced into a devastating decision that will bring the curtains down on Venom and Eddie\'s last dance.', 'Sci-Fi', 109, '7.2', '2024-10-24', 'https://image.tmdb.org/t/p/w1280/vGXptEdgZIhPg3cGlc7e8sNPC2e.jpg', 'published', 17.00, '2026-06-07 00:09:21', NULL, 17),
(68, 'World War Z', 'Life for former United Nations investigator Gerry Lane and his family seems content. Suddenly, the world is plagued by a mysterious infection turning whole human populations into rampaging mindless zombies. After barely escaping the chaos, Lane is persuaded to go on a mission to investigate this disease. What follows is a perilous trek around the world where Lane must brave horrific dangers and long odds to find answers before human civilization falls.', 'Sci-Fi', 116, '7.5', '2013-06-21', 'https://image.tmdb.org/t/p/w1280/aCnVdvExw6UWSeQfr0tUH3jr4qG.jpg', 'published', 12.00, '2026-06-07 00:10:04', NULL, 17),
(69, 'Sonic the Hedgehog 3', 'Sonic, Knuckles, and Tails reunite against a powerful new adversary, Shadow, a mysterious villain with powers unlike anything they have faced before. With their abilities outmatched in every way, Team Sonic must seek out an unlikely alliance in hopes of stopping Shadow and protecting the planet.', 'Sci-Fi', 110, '8.4', '2004-12-26', 'https://image.tmdb.org/t/p/w1280/d8Ryb8AunYAuycVKDp5HpdWPKgC.jpg', 'published', 18.00, '2026-06-07 00:11:02', NULL, 17),
(70, 'Star Wars', 'Princess Leia is captured and held hostage by the evil Imperial forces in their effort to take over the galactic Empire. Venturesome Luke Skywalker and dashing captain Han Solo team together with the loveable robot duo R2-D2 and C-3PO to rescue the beautiful princess and restore peace and justice in the Empire.', 'Sci-Fi', 122, '9.0', '1977-05-01', 'https://image.tmdb.org/t/p/w1280/6FfCtAuVAW8XJjZ7eWeLibRLWTw.jpg', 'published', 22.00, '2026-06-07 00:11:56', NULL, 17),
(71, 'Project Hail Mary', 'Science teacher Ryland Grace wakes up on a spaceship light years from home with no recollection of who he is or how he got there. As his memory returns, he begins to uncover his mission: solve the riddle of the mysterious substance causing the sun to die out. He must call on his scientific knowledge and unorthodox ideas to save everything on Earth from extinction.', 'Sci-Fi', 159, '9.4', '2026-03-20', 'https://image.tmdb.org/t/p/w1280/yihdXomYb5kTeSivtFndMy5iDmf.jpg', 'published', 26.00, '2026-06-07 00:12:48', NULL, 17),
(72, 'The Lord of the Rings: The Return of the King', 'As armies mass for a final battle that will decide the fate of the world--and powerful, ancient forces of Light and Dark compete to determine the outcome--one member of the Fellowship of the Ring is revealed as the noble heir to the throne of the Kings of Men. Yet, the sole hope for triumph over evil lies with a brave hobbit, Frodo, who, accompanied by his loyal friend Sam and the hideous, wretched Gollum, ventures deep into the very dark heart of Mordor on his seemingly impossible quest to destroy the Ring of Power.​', 'Fantasy', 201, '8.9', '2003-12-19', 'https://image.tmdb.org/t/p/w1280/rCzpDGLbOoPwLjy3OAm5NUPOTrC.jpg', 'published', 23.00, '2026-06-07 00:15:43', NULL, 17),
(73, 'Harry Potter and the Philosopher\'s Stone', 'Harry Potter has lived under the stairs at his aunt and uncle\'s house his whole life. But on his 11th birthday, he learns he\'s a powerful wizard—with a place waiting for him at the Hogwarts School of Witchcraft and Wizardry. As he learns to harness his newfound powers with the help of the school\'s kindly headmaster, Harry uncovers the truth about his parents\' deaths—and about the villain who\'s to blame.', 'Fantasy', 152, '8.3', '2001-11-22', 'https://image.tmdb.org/t/p/w1280/wuMc08IPKEatf9rnMNXvIDxqP4W.jpg', 'published', 19.00, '2026-06-07 00:16:28', NULL, 17),
(74, 'Pirates of the Caribbean: The Curse of the Black Pearl', 'When wily pirate Captain Barbossa seizes Jack Sparrow’s beloved ship, the Black Pearl, and kidnaps the governor’s daughter, Elizabeth Swann, blacksmith Will Turner reluctantly teams up with the unpredictable pirate Jack to rescue her—only to uncover a terrifying curse that turns Barbossa’s crew into the undead.', 'Fantasy', 143, '8.4', '2003-07-09', 'https://image.tmdb.org/t/p/w1280/kvDwL2gTf6yxujbsWbsGQB3Z9Wa.jpg', 'published', 20.00, '2026-06-07 00:17:22', NULL, 17),
(75, 'Coraline', 'Wandering her rambling old house in her boring new town, 11-year-old Coraline discovers a hidden door to a strangely idealized version of her life. In order to stay in the fantasy, she must make a frighteningly real sacrifice.', 'Fantasy', 100, '8.5', '2009-02-06', 'https://image.tmdb.org/t/p/w1280/4jeFXQYytChdZYE9JYO7Un87IlW.jpg', 'published', 16.99, '2026-06-07 00:18:35', NULL, 17),
(76, 'How to Train Your Dragon', 'On the rugged isle of Berk, where Vikings and dragons have been bitter enemies for generations, Hiccup stands apart, defying centuries of tradition when he befriends Toothless, a feared Night Fury dragon. Their unlikely bond reveals the true nature of dragons, challenging the very foundations of Viking society.', 'Fantasy', 125, '8.7', '2025-06-12', 'https://image.tmdb.org/t/p/w1280/q5pXRYTycaeW6dEgsCrd4mYPmxM.jpg', 'published', 21.00, '2026-06-07 00:19:57', NULL, 17),
(77, 'Fall', 'For best friends Becky and Hunter, life is all about conquering fears and pushing limits. But after they climb 2,000 feet to the top of a remote, abandoned radio tower, they find themselves stranded with no way down. Now Becky and Hunter\'s expert climbing skills will be put to the ultimate test as they desperately fight to survive the elements, a lack of supplies, and vertigo-inducing heights.', 'Thriller', 107, '8.2', '2022-08-12', 'https://image.tmdb.org/t/p/w1280/KHOTPQMjRznOSGw4bmGEvljjHM.jpg', 'published', 17.00, '2026-06-07 00:24:39', NULL, 17),
(78, 'Mission: Impossible - The Final Reckoning', 'Ethan Hunt and team continue their search for the terrifying AI known as the Entity — which has infiltrated intelligence networks all over the globe — with the world\'s governments and a mysterious ghost from Hunt\'s past on their trail. Joined by new allies and armed with the means to shut the Entity down for good, Hunt is in a race against time to prevent the world as we know it from changing forever.', 'Thriller', 170, '7.6', '2025-05-22', 'https://image.tmdb.org/t/p/w1280/z53D72EAOxGRqdr7KXXWp9dJiDe.jpg', 'published', 21.00, '2026-06-07 00:25:34', NULL, 17),
(79, 'Fight Club', 'A ticking-time-bomb insomniac and a slippery soap salesman channel primal male aggression into a shocking new form of therapy. Their concept catches on, with underground \"fight clubs\" forming in every town, until an eccentric gets in the way and ignites an out-of-control spiral toward oblivion.', 'Thriller', 139, '8.8', '1999-01-13', 'https://image.tmdb.org/t/p/w1280/jSziioSwPVrOy9Yow3XhWIBDjq1.jpg', 'published', 23.98, '2026-06-07 00:26:36', NULL, 17);

-- --------------------------------------------------------

--
-- Table structure for table `movie_feedback`
--

CREATE TABLE `movie_feedback` (
  `feedback_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movie_feedback`
--

INSERT INTO `movie_feedback` (`feedback_id`, `movie_id`, `user_id`, `rating`, `comment`, `created_at`, `updated_at`, `status`) VALUES
(1, 12, 25, 5, 'Great ! interestinggggg', '2026-05-10 02:39:59', '2026-06-01 12:58:27', 'approved'),
(2, 20, 25, 1, 'Boring....', '2026-06-01 13:08:51', '2026-06-01 13:19:49', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `showtimes`
--

CREATE TABLE `showtimes` (
  `showtime_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `show_time` varchar(20) NOT NULL COMMENT '10:00 AM, 4:00 PM, 7:30 PM, 10:30 PM',
  `status` enum('active','cancelled') DEFAULT 'active',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `showtimes`
--

INSERT INTO `showtimes` (`showtime_id`, `movie_id`, `show_time`, `status`, `updated_by`, `updated_at`) VALUES
(1, 6, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(3, 10, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(4, 11, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(5, 12, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(6, 16, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(7, 20, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(8, 21, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(9, 23, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(10, 37, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(11, 38, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(12, 39, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(13, 40, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(14, 41, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(15, 42, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(16, 44, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(17, 45, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(18, 46, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(20, 48, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(21, 49, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(22, 50, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(23, 51, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(24, 52, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(25, 53, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(26, 54, '10:00 AM', 'active', NULL, '2026-06-01 20:21:37'),
(32, 6, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(34, 10, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(35, 11, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(36, 12, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(37, 16, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(38, 20, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(39, 21, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(40, 23, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(41, 37, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(42, 38, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(43, 39, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(44, 40, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(45, 41, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(46, 42, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(47, 44, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(48, 45, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(49, 46, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(51, 48, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(52, 49, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(53, 50, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(54, 51, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(55, 52, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(56, 53, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(57, 54, '4:00 PM', 'active', NULL, '2026-06-01 20:21:37'),
(63, 6, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(65, 10, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(66, 11, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(67, 12, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(68, 16, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(69, 20, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(70, 21, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(71, 23, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(72, 37, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(73, 38, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(74, 39, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(75, 40, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(76, 41, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(77, 42, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(78, 44, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(79, 45, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(80, 46, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(81, 47, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(82, 48, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(83, 49, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(84, 50, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(85, 51, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(86, 52, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(87, 53, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(88, 54, '7:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(94, 6, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(96, 10, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(97, 11, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(98, 12, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(99, 16, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(100, 20, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(101, 21, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(102, 23, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(103, 37, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(104, 38, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(105, 39, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(106, 40, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(107, 41, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(108, 42, '11.00 PM', 'active', 17, '2026-06-01 20:32:54'),
(109, 44, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(110, 45, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(111, 46, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(113, 48, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(114, 49, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(115, 50, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(116, 51, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(117, 52, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(118, 53, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(119, 54, '10:30 PM', 'active', NULL, '2026-06-01 20:21:37'),
(125, 7, '10:00 AM', 'active', NULL, '2026-06-01 20:38:44'),
(126, 47, '4:00 PM', 'active', NULL, '2026-06-01 20:38:44'),
(128, 7, '10:30 PM', 'active', NULL, '2026-06-01 20:38:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `role` enum('user','staff','admin') DEFAULT 'user',
  `early_access` tinyint(1) DEFAULT 0,
  `early_access_days` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `early_access`, `early_access_days`) VALUES
(17, 'tasha', 'tashazzflee9@gmail.com', 'tasha123', 'admin', 0, NULL),
(19, 'lia', 'lia@gmail.com', 'lia123', 'user', 0, NULL),
(20, 'Ahmad', 'Ahmad@gmail.com', 'Ahmad123', 'user', 0, NULL),
(21, 'Sabrina', 'sabrina@gmail.com', 'Sabrina123', 'user', 0, NULL),
(22, 'Jennie', 'jennie@gmail.com', 'jennie123', 'staff', 0, NULL),
(25, 'Dahyun', 'dahyun@gmail.com', 'dahyun123', 'user', 1, 30);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`wishlist_id`, `user_id`, `movie_id`) VALUES
(3, 25, 6),
(2, 25, 35),
(4, 25, 42);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `badge_id` (`badge_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indexes for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`code_id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `generated_for` (`generated_for`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `used_by` (`used_by`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`movie_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `movie_feedback`
--
ALTER TABLE `movie_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `unique_user_movie` (`user_id`,`movie_id`),
  ADD KEY `idx_movie_id` (`movie_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `showtimes`
--
ALTER TABLE `showtimes`
  ADD PRIMARY KEY (`showtime_id`),
  ADD UNIQUE KEY `unique_movie_time` (`movie_id`,`show_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`movie_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `code_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `movie_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `movie_feedback`
--
ALTER TABLE `movie_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `showtimes`
--
ALTER TABLE `showtimes`
  MODIFY `showtime_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD CONSTRAINT `discount_codes_ibfk_1` FOREIGN KEY (`generated_for`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `discount_codes_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `discount_codes_ibfk_3` FOREIGN KEY (`used_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `movie_feedback`
--
ALTER TABLE `movie_feedback`
  ADD CONSTRAINT `movie_feedback_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `movie_feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `showtimes`
--
ALTER TABLE `showtimes`
  ADD CONSTRAINT `fk_showtimes_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
