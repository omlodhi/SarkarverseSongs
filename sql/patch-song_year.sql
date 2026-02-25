-- Add song_year column for efficient year filtering
ALTER TABLE /*_*/sarkarverse_songs
ADD COLUMN song_year VARCHAR(4) NOT NULL DEFAULT '' AFTER song_date;

-- Populate song_year from existing song_date values
UPDATE /*_*/sarkarverse_songs
SET song_year = SUBSTRING(song_date, 1, 4)
WHERE song_date REGEXP '^[0-9]{4}';

UPDATE /*_*/sarkarverse_songs
SET song_year = SUBSTRING(song_date, -4)
WHERE song_year = '' AND song_date REGEXP '[0-9]{4}$';
