-- Allow NULL in league_id for registration_requests to support teams without leagues
ALTER TABLE `registration_requests` MODIFY `league_id` int(11) NULL;
