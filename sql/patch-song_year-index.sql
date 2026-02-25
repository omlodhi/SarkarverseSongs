-- Add index for song_year column for efficient filtering
CREATE INDEX /*i*/idx_song_year ON /*_*/sarkarverse_songs (song_year);
