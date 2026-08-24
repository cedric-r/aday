# User Guide

## Registration

1. Visit **http://localhost:8765/register** (or the deployed URL).
2. Fill in the form:
   - **Username** — 3–30 characters, letters, numbers, and underscores only.
   - **Display name** — your name as shown on the site.
   - **Substack URL** — your Substack page (displayed publicly on your photographer profile).
   - **Email** — used for admin contact; not publicly displayed.
   - **Password** — minimum 8 characters.
   - **Timezone** — select your local IANA timezone from the dropdown (pre-filled to your browser timezone).
   - **Captcha** — answer the question shown to confirm you are human.
3. Submit the form.
4. You will see a "Check your email" confirmation. Your account is now **Pending**.

> **Registration is disabled on the event day.** If you try to register after the event has started, you will see a "Registration closed" message.

---

## Awaiting Approval

After registering, the admin receives an email with a validation link. Once they click it, your account status changes to **Validated** and you can log in.

You will not receive a confirmation email yourself — contact the event organiser if approval is delayed.

---

## Logging In

1. Visit **/login** (or click **Log in** in the menu).
2. Enter your username and password.
3. On success, you are redirected to the home page. The menu updates to show **Post** and **Log out**.

> If you see "Account pending approval", your account has not yet been validated by the admin.

---

## Posting a Photo

You can post photos during the event day in **your local timezone** — from 00:00 to 23:59:59.

1. Click **Post** in the menu (visible to logged-in, validated users).
2. The posting form shows a banner indicating whether your window is open, with a countdown to midnight in your timezone.
3. Choose a photo file — accepted formats: **JPEG, PNG, WEBP**, max **15 MB**.
4. Write a description (no length limit — tell the story behind the shot).
5. Click **Submit**.
6. On success, a confirmation message appears with a link to the home page where your photo will be visible within 1 minute.

**If submission is rejected:**
- "Posting window closed" — it is past 23:59 in your local timezone, or it is not the event date.
- "Invalid file" — the file is too large or not an accepted image format.

---

## Browsing the Home Feed

The **home page** (`/`) shows all photos from all photographers in reverse chronological order (newest first).

- The feed **auto-refreshes every minute** — new photos appear at the top without reloading the page.
- Scroll down to see older photos. Click **Load more** at the bottom to continue.
- Each photo card shows:
  - The photo
  - Photographer's name (links to their profile) and Substack link
  - Description
  - Timestamp in your local time

---

## Photographer Index

Click **Index** in the menu to see the **Photographer Index** (`/index`):

- All validated participants listed **A–Z** by display name.
- Each entry shows their name, Substack link, and photo count.
- Click a name to go to their individual photographer page.

---

## Photographer Profile Page

URL: `/photographers/{username}`

Shows a single photographer's:
- Name and Substack link
- All their photos in reverse chronological order (newest first)

If a photographer has not yet posted any photos, the page shows "No photos yet."
