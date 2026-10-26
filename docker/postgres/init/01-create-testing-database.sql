-- Runs once, when the Postgres volume is first initialized.
-- The test suite (phpunit.xml) uses this separate database so tests never touch development data.
CREATE DATABASE weather_api_testing;
