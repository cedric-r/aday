# Admin Guide

## Accessing the Admin Panel

Log in with an admin account, then click **Admin** in the menu. Direct URL: `/admin`.

Only users with the `is_admin` flag can access this page. Non-admin users are redirected.

The admin panel has four tabs: **Users**, **Event Date**, **Submissions**, and **Embed**.

---

## First Admin Setup

On a fresh installation, visit `/setup.php` to create the first admin account. This page is only available once — it locks permanently after the first admin is created.

If you need to reset the first admin (e.g., lost credentials):
```bash
php scripts/reset_admin.php
```
This clears the lock flag and allows `/setup.php` to run again.

---

## Managing Users

### Viewing Users

The **Users** tab shows all registered users in a table:

| Column | Description |
|---|---|
| Username | Login handle |
| Name | Display name shown on the site |
| Email | Contact email |
| Substack URL | The user's public Substack page (clickable; `—` when not set) |
| Bio | Optional short blurb shown on their public profile (editable in Edit) |
| Timezone | User's IANA timezone (affects posting window) |
| Status | `pending` (awaiting approval), `validated`, or `disabled` |
| Is Admin | Whether the user has admin privileges |
| Actions | Edit · Delete · Approve |

> The Substack URL is only editable through **Add User** / **Edit User** (below),
> and is also shown publicly on the user's photographer profile page.

### Approving a Pending User

Click **Approve** next to a user with `pending` status. Their status changes to `validated` immediately, allowing them to log in and post photos.

Alternatively, clicking the link in the registration notification email approves the user automatically (redirects to the admin panel). The link is **single-use** and expires after **72 hours**.

### Adding a User

Click **Add User** to open the creation form:
- Fill in all fields (username, name, Substack URL, email, password, timezone).
- The **Substack URL** must be a valid `http(s)` link (other schemes are rejected).
- The **timezone** must be a valid IANA identifier.
- Toggle **Is Admin** to grant admin privileges.
- Admin-created users are set to `validated` automatically — no approval step.

### Editing a User

Click **Edit** to update: name, email, Substack URL, timezone, status, is_admin.

- Leave **Password** blank to keep the existing password.
- Enter a new password (min 8 chars) to reset it.
- `substack_url`: clear the field to remove the Substack link.
- `timezone`/`status` are validated (pending, validated or disabled) on save.

You cannot remove the admin flag from yourself if you are the only admin.

### Deleting a User

Click **Delete** and confirm. The user is removed permanently, **including
their uploads directory** (photos + thumbnails) on disk.

Restrictions:
- You cannot delete your own account.
- You cannot delete the last remaining admin.

---

## Setting the Event Date

Click the **Event Date** tab.

- Use the date picker to select the event date.
- **Keep submissions open after the event date** — a switch (default **off**).
  When enabled, the posting window stays open from the event date onward
  (for late submitters, e.g. film photographers). When off, photos can only be
  submitted on the event date itself (in each user's local timezone).
- Click **Save**. Both the date and the toggle are stored immediately and affect:
  - **Registration** — disabled on this date.
  - **Posting window** — photos can only be submitted on this date (unless late submissions are enabled).
  - A warning appears if the date is in the past.

---

## Monitoring Submissions

Click the **Submissions** tab.

- All photo submissions are listed in a table (newest first).
- The table **polls every 60 seconds** — new photos appear without refreshing the page.
- Columns: thumbnail, photographer name, description snippet, gear/EXIF, posted timestamp, flags, actions.
- Toggle between **Flat view** (all photos interleaved) and **Grouped by photographer**.
- Each row shows the total photo count per photographer in grouped view.

### Per-photo actions

Each row has three toggle buttons:

- **⭐ Highlight** — toggles the photo in the home-page Highlights strip.
- **👁 Hide / show** — "unlists" the photo from every public surface (home feed,
  grid, highlights, photographer page, embed, and its own `/photos/:id` page —
  which returns 404). The file stays on disk, so this is the gentler alternative
  to deletion for borderline duplicates.
- **🗑 Delete** — removes the photo from the database, plus its **original and
  thumbnail** files from disk. Permanent.

### Deleting a Submission

Click the delete icon on any row. The photo is removed from the database and
from disk (original + thumbnail). This action is permanent.

### Wrap-up email to participants

Click **Email participants** to send the post-event wrap-up email once. It goes
to every **validated** participant's address and includes the gallery link.
A `wrapup_sent` guard prevents double-sending (even if the button and a cron
job race), and if the mail relay fails the guard is released so you can retry.
For hands-off operation, run `php scripts/send_wrapup.php` nightly from cron.

---

## Exporting Photos

At the top of the **Submissions** tab, click **Export All**.

This downloads a single ZIP file (`aday-all-photos.zip`) containing one subfolder per photographer:

```
alice/
  abc123.jpg
  def456.png
  descriptions.txt

bob/
  ghi789.webp
  descriptions.txt
```

Each `descriptions.txt` lists entries in posting order:
```
abc123.jpg: Morning light over the harbour...
def456.png: Golden hour from the rooftop...
```

> The export includes all photos posted up to the moment you click the button. Run it again after the event closes to capture any late submissions.

### Metadata export (CSV / JSON)

At the top of the **Submissions** tab, click **Export metadata (CSV)** (or
use `?format=json`) for a machine-readable export of **every photo's metadata**
— id, username, name, timezone, Substack URL, filename, posted time,
description, highlight, gear, and full EXIF block (make, model, focal,
aperture, shutter, ISO). CSV cells that would start with a spreadsheet
formula character (=, +, -, @) are prefixed with `'` to prevent
**CSV formula injection** when opened in Excel/Sheets.

---

## Email Tab

Click the **Email** tab to send a one-off broadcast message (e.g. a reminder
that the event date is approaching) to registered users.

1. **Recipients** — pick **Validated participants** (default) or **All
   registered users**. A live counter shows how many people will be emailed
   as you switch.
2. **Subject** — required, max 200 chars, single line.
3. **Message** — required, max 5000 chars.
4. **Send email** — a confirmation dialog shows the recipient count before
   anything is sent.

The result reports how many emails were delivered (`sent`) and how many failed
(e.g. an unreachable SMTP relay). Admins are never recipients.

---

## Messages Tab

Click the **Messages** tab to post a notification to the participants.

1. **Subject** — required, max 200 chars, single line.
2. **Message** — required, max 5000 chars.
3. **Post notification** — a confirmation dialog shows how many validated
   participants will see it.

Notifications appear immediately on each participant's **Notifications** tab
(`/notifications`). Nothing is emailed from here — use the **Email** tab if you
also want an email. The **Sent notifications** list below the composer shows
everything posted, and each entry has a **Delete** button to retract it (the
message disappears from every participant's tab).

Only admins can post or delete. Participants can read but never send.

---

## Validating a user

New registrations arrive as **pending**. Approve them by either:

- Clicking the **Validate** link in the admin notification email (single-use,
  72h expiry), or
- Editing the user in the **Users** tab and setting **Status → validated**.

Either way the participant is emailed an **approval confirmation** the moment
their account becomes validated (including when a user is created directly as
validated from the **Add user** form). Clicking an already-used link shows the
same success redirect without re-sending the email.

Pending users can't post until validated.

---

## Embed Tab

Click the **Embed** tab to generate embeddable snippets for any site that
allows arbitrary iframes (Notion, self-hosted blogs, Webflow, etc.):

1. **Photographer dropdown** — pick a single photographer's gallery
   (or "Everyone" for the whole feed).
2. **Dark / Light toggle** — the dark variant matches dark blogs
   (`?theme=dark`).
3. Click **Copy iframe** (or copy the plain link) — both regenerate as you
   change the options.

The iframe points at `/embed` (header-less, auto-refreshing). `/embed` is the
**only** page that allows being framed — every other page sends
`Content-Security-Policy: frame-ancestors 'none'`, so the site can't be
clickjack-embedded elsewhere.

> **Substack caveat — Substack does NOT allow raw HTML/iframes in posts**
> (its editor only embeds whitelisted providers), so the iframe snippet will
> not work inside a Substack post. What **does** work on Substack is a
> **card-style link preview**: paste a plain URL and Substack fetches its
> OpenGraph metadata and shows a rich card.
> - For a **photo** card, link to the photo's own page
>   **`https://aday.photoni.st/photos/<id>`** (its `og:image` is the actual photo).
> - Pasting **`/embed`** (or `/embed?photographer=username`) also shows a
>   branded "Document Your Life" card now — its image is the site logo, and
>   the title personalises to the photographer when the `?photographer=`
>   param is present.

---

## Admins as Participants

Admin users are full participants. If your account has both `is_admin = 1` and `status = validated`, you can:

- Post photos using the **Post** page (visible in the menu when logged in as any validated user).
- Your photos appear in the home feed and on your photographer profile page just like any other participant.

> The **Admin** and **Post** menu items are both shown for admin users.
