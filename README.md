# SarkarverseSongs

A MediaWiki extension that provides a parser function for storing and displaying Sarkarverse song information with a searchable dashboard and API.

## Requirements

- MediaWiki 1.44 or later

## Installation

1. Download and place the files in a directory called `SarkarverseSongs` in your `extensions/` folder.

2. Add the following line to your `LocalSettings.php`:

   ```php
   wfLoadExtension( 'SarkarverseSongs' );
   ```

3. Run the update script to create the necessary database tables:

   ```bash
   php maintenance/run.php update
   ```

4. Done! Navigate to `Special:Version` on your wiki to verify the extension is installed.

## Features

### Parser Function

The extension provides a `{{#song:}}` parser function to define songs on wiki pages. Use it in a table format:

```wikitext
{| class="wikitable"
|-
! Number !! Date !! First line(s) !! Theme !! Language !! Music
|-
{{#song:0001|1982 September 14|Bandhu he niye calo|Longing for the Great|Bengali|Dadra}}
|-
{{#song:0002|1982 September 14|E path jadi na shes hay|Determination|Bengali|Kaharva}}
|}
```

**Parameters (in order):**
1. **Number** - Unique song identifier
2. **Date** - Date of composition
3. **Title** - First line(s) of the song (links to the song's wiki page)
4. **Theme** - Thematic category of the song
5. **Language** - Language of the song
6. **Music** - Musical style/rhythm

The parser function automatically:
- Stores song data in the database when the page is saved
- Creates links to individual song pages
- Fetches and stores categories from linked song pages

### Special Page

Access `Special:SarkarverseSongs` to browse all songs with filtering options:

- **Category filter** - Filter by categories from individual song pages
- **Theme filter** - Filter by song theme
- **Language filter** - Filter by language
- **Pagination** - Navigate through large song collections (50 songs per page)

### API Module

Query songs programmatically via the MediaWiki API:

```
api.php?action=sarkarversesongs
```

**Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `theme` | string | null | Filter by theme |
| `language` | string | null | Filter by language |
| `category` | string | null | Filter by category |
| `limit` | integer | 50 | Number of results (1-500) |
| `offset` | integer | 0 | Offset for pagination |

**Examples:**

```
# Get all songs
api.php?action=sarkarversesongs

# Get songs with "Enlightenment" theme
api.php?action=sarkarversesongs&theme=Enlightenment

# Get first 10 Bengali songs
api.php?action=sarkarversesongs&language=Bengali&limit=10

# Get songs in a specific category
api.php?action=sarkarversesongs&category=Prabhat%20Samgiita
```

**Response includes:**
- `total` - Total count of matching songs
- `songs` - Array of song objects
- `themes` - All available themes
- `languages` - All available languages
- `categories` - All available categories

## Database Schema

The extension creates two tables:

### `sarkarverse_songs`
Stores song metadata:
- `song_id` - Primary key
- `song_number` - Unique song identifier
- `song_date` - Composition date
- `song_title` - First line(s) / title
- `song_theme` - Theme
- `song_language` - Language
- `song_music` - Musical style
- `song_page_id` - Wiki page ID where the song is defined
- `song_page_title` - Wiki page title

### `sarkarverse_song_categories`
Stores categories from individual song pages:
- `ssc_id` - Primary key
- `ssc_song_number` - References song number
- `ssc_category` - Category name

## License

GPL-2.0-or-later

## Author

Om Prakash
