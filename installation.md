# Installation

Follow these steps to get the project up and running:

1. **Install PHP**
   Make sure you have the latest version of PHP installed on your system.
- *Windows:* Download from the official PHP website.
- *Mac (Homebrew):* `brew install php`
- *Linux (Ubuntu/Debian):* `sudo apt install php`

2. **Install Streamlink**
   Streamlink is required to handle YouTube streams. Install it via `pip`:
   ```bash
   pip install streamlink
   ```

3. **Get YouTube Data API v3 Key**
- Go to the [Google Cloud Console](https://console.cloud.google.com).
- Create a new project (or use an existing one).
- Enable the **YouTube Data API v3**.
- Create credentials (**API Key**)
- Save API key to main.php (`define('YOUTUBE_API_KEY', 'Your api key');`)

4. **Run the Server**
   Start the local PHP development server by running the following command in your terminal:
   ```bash
   php -S localhost:8000 main.php
   ```
   Open `http://localhost:8000` in your browser.