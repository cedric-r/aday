# Admin Guide

## Accessing the Admin Panel

Log in with an admin account, then click **Admin** in the menu. Direct URL: `/admin`.

Only users with the `is_admin` flag can access this page. Non-admin users are redirected.

The admin panel has three tabs: **Users**, **Event Date**, and **Submissions**.

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
| Timezone | User's IANA timezone (affects posting window) |
| Status | `pending` (awaiting approval) or `validated` |
| Is Admin | Whether the user has admin privileges |
| Actions | Edit · Delete · Approve |

### Approving a Pending User

Click **Approve** next to a user with `pending` status. Their status changes to `validated` immediately, allowing them to log in and post photos.

Alternatively, clicking the link in the registration notification email approves the user automatically (redirects to the admin panel).

### Adding a User

Click **Add User** to open the creation form:
- Fill in all fields (username, name, Substack URL, email, password, timezone).
- Toggle **Is Admin** to grant admin privileges.
- Admin-created users are set to `validated` automatically — no approval step.

### Editing a User

Click **Edit** to update: name, email, Substack URL, timezone, status, is_admin.

- Leave **Password** blank to keep the existing password.
- Enter a new password (min 8 chars) to reset it.
- `substack_url`: clear the field to remove the Substack link.

You cannot remove the admin flag from yourself if you are the only admin.

### Deleting a User

Click **Delete** and confirm. The user is removed permanently.

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
- Columns: photographer name, description snippet, posted timestamp, thumbnail.
- Toggle between **Flat view** (all photos interleaved) and **Grouped by photographer**.
- Each row shows the total photo count per photographer in grouped view.

### Deleting a Submission

Click the delete icon on any row. The photo is removed from the database and from disk. This action is permanent.

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

---

## Admins as Participants

Admin users are full participants. If your account has both `is_admin = 1` and `status = validated`, you can:

- Post photos using the **Post** page (visible in the menu when logged in as any validated user).
- Your photos appear in the home feed and on your photographer profile page just like any other participant.

> The **Admin** and **Post** menu items are both shown for admin users.
