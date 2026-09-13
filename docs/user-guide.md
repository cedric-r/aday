# User Guide

## Registration

1. Visit **https://aday.photoni.st/register** (or `http://localhost:8765/register` in local dev).
2. Fill in the form:
   - **Username** — 3–30 characters, letters, numbers, and underscores only.
   - **Display name** — your name as shown on the site.
   - **Substack URL** — your Substack page (displayed publicly on your photographer profile). Must be a valid `http(s)` link.
   - **Short bio** — optional, max 500 characters, shown on your photographer profile.
   - **Email** — used for admin contact; not publicly displayed.
   - **Password** — minimum 8 characters.
   - **Timezone** — select your local IANA timezone from the dropdown (pre-filled to your browser timezone).
   - **Captcha** — answer the question shown to confirm you are human.
3. Submit the form.
4. You will see a "Check your email" confirmation. Your account is now **Pending**.

> **Registration is disabled on the event day.** If you try to register after the event has started, you will see a "Registration closed" message.

---

## Awaiting Approval

After registering, the admin receives an email with a validation link. Once they click it, your account status changes to **Validated** and you can log in. The link is **single-use** and expires after **72 hours** — if it lapses, ask the admin to create you manually or re-register.

You will not receive a confirmation email yourself — contact the event organiser if approval is delayed.

---

## Logging In

1. Visit **/login** (or click **Log in** in the menu).
2. Enter your username and password.
3. On success, you are redirected to the home page. The menu updates to show **Post** and **Log out**.

> If login fails with "Invalid credentials or account pending approval", your
> account has not yet been validated by the admin (or the credentials are
> wrong — the site deliberately doesn't reveal which).

---

## Posting a Photo

You can post photos during the event day in **your local timezone** — from 00:00 to 23:59:59.

> If the organiser has enabled **late submissions** (for film photographers, etc.),
> the window stays open after the event date as well — you can post any day from
> the event date onward.

1. Click **Post** in the menu (visible to logged-in, validated users).
2. The posting form shows a banner indicating whether your window is open, with a countdown to midnight in your timezone.
3. Choose a photo file — accepted formats: **JPEG, PNG, WEBP**, max **15 MB**
   (images larger than **8000 px / 40 megapixels** are rejected).
4. Write a description (up to **2000 characters** — tell the story behind the shot).
5. **Optional: gear** — a free-text gear note ("Hasselblad 500C/M · 80mm · Portra 400")
   for film/manual setups. Digital uploads also get EXIF captured automatically
   (make, model, focal length, aperture, shutter, ISO).
6. Click **Submit**.
7. On success, a confirmation message appears with a link to the home page where your photo will be visible within 1 minute.

**If submission is rejected:**
- "Posting window closed" — it is past the event date (and late submissions are off), or the event date has not arrived yet.
- "Invalid file" — the file is too large or not an accepted image format.

---

## Browsing the Home Feed

The **home page** (`/`) shows all photos from all photographers in reverse chronological order (newest first).

- The feed **auto-refreshes every minute** — new photos appear at the top without reloading the page.
- Above the feed you'll see a **stats strip** ("N photos so far · N photographers · N time zones" + a 48-hour posting pulse)
  and an **admin-curated Highlights strip** (⭐ photos) when there are any.
- Use the **Follow the sun — local hour** dropdown to see what every photographer
  was shooting at, say, 07:00 *in their own timezone* — Tokyo's morning first,
  the US west coast last. Each card shows the photographer's local time. Choose
  **All hours** to return to the normal feed.
- Click the 🎲 button to open a random photo from the archive.
- Use the **Cards ⇄ Grid** toggle (top-right of the feed) to switch between the
  vertical card list and a contact-sheet grid of thumbnails. Your choice is remembered.
- Scroll down to see older photos. Click **Load more** at the bottom to continue.
- Each photo card shows:
  - The photo
  - Photographer's name (links to their profile) and Substack link
  - Description
  - Gear/EXIF line when available (e.g. "Canon EOS 5D · 50mm · f/1.8 · 1/125s · ISO 400")
  - A ⭐ badge for admin-highlighted photos
  - Timestamp in your local time

### Lightbox

Click any photo to open the **lightbox** — a full-screen viewer with:

- Larger image + all photo details
- **← / →** arrow keys (or on-screen buttons) to move between photos
- **Space** or the ▶ button to start/pause a **slideshow** that advances every 5 seconds (wraps around) — handy for projecting at a wrap-up meetup
- **Esc** (or ✕) to close

### Sharing a photo

Every photo has its own shareable URL:

- **`/?photo=42`** — deep link that opens the lightbox on that photo (used by the
  status bar copy-link button on the photo card)
- **`/photos/42`** — a standalone photo page. When shared on Substack, X.com,
  iMessage or any link-previewing app, it shows a rich card with the image,
  photographer and description (OpenGraph/Twitter meta tags).

**Before the event** — if no photos have been posted yet and the event date is in
the future, the home page shows "No photos yet. Check back soon!", the event
date, and the site logo.

---

## Sharing a photographer's gallery

The event organiser can hand you an iframe snippet for your gallery
(`/embed?photographer=username`) that works on any site that permits arbitrary
iframes (your own blog, Notion, etc.).

**On Substack** raw iframes are not supported, so use a **link preview**
instead: paste `https://aday.photoni.st/photos/<your-photo-id>` into a post and
Substack renders a rich **photo** card (it fetches the page's OpenGraph tags).
Pasting the whole-gallery link
`https://aday.photoni.st/embed?photographer=username` also shows a branded card
("Document Your Life") — its image is the site logo, so it's a gallery card
rather than a specific photo.

---

## Photographer Index

Click **Index** in the menu to see the **Photographer Index** (`/index`):

- All validated participants listed **A–Z** by display name.
- Each entry shows their name, Substack link, and photo count.
- Click a name to go to their individual photographer page.

---

## Notifications

URL: `/notifications` (visible in the menu once you're logged in)

Messages from the organisers — event reminders, schedule changes, anything the
team needs to tell everyone taking part. Newest first. The page is read-only:
participants can read notifications but cannot send them (only organisers can).

If your account is still awaiting validation, the page tells you so — you'll
see notifications as soon as an organiser approves you.

---

## Photographer Profile Page

URL: `/photographers/{username}`

Shows a single photographer's:
- Name and Substack link
- Optional bio
- All their photos in reverse chronological order (newest first)

**Downloading your own photos** — when you view your *own* profile while logged
in, a **⬇ Download my photos (ZIP)** button appears above the feed. It gives you
a ZIP of all your visible photos plus a `descriptions.txt` manifest.

**Deleting your own photos** — each of your photos also shows a **🗑 delete**
button (only on your own profile). Deleting asks for confirmation and
permanently removes the photo, its thumbnail, and its metadata.

If a photographer has not yet posted any photos, the page shows "No photos yet."
