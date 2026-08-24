# A Day In The Life — Dynamic Photo Publishing System

**Feature**: aday-photo-publishing | **Stack**: Vanilla PHP + React + SQLite

---

## Problem

A 1-day event needs a shared photo publishing platform where multiple photographers post throughout their local day, and a public home page displays all photos in real time.

## Business Value

Enables distributed photographers across timezones to contribute to a single live event stream without synchronisation overhead or a third-party platform.

## Solution

Vanilla PHP backend (SQLite), React frontend. Timezone-aware posting window enforced server-side. Admin validates users before event day. Home page polls for new photos every minute.

---

## User Stories

### US-1: User Registration
**As a** prospective photographer  
**I can** register with username, name, Substack URL, password, email, and timezone  
**So that** I can participate on event day

**Acceptance Criteria**:
- **Given** registration form is open, **When** user submits valid details + captcha, **Then** account is created in Pending state and admin receives validation email with approve link
- **Given** captcha is shown, **When** page loads, **Then** one question is selected at random from a pool of 50
- **Given** event day has arrived, **When** user visits registration page, **Then** registration is disabled

### US-2: Admin — User Management & Event Config
**As an** admin user  
**I can** validate/manage users and set the event date  
**So that** only approved photographers participate and the event window is correctly defined

**Acceptance Criteria**:
- **Given** admin visits admin page, **When** they click a validation link or manage users inline, **Then** they can add, edit, delete, or approve users
- **Given** admin sets event date, **When** saved, **Then** system uses that date as the posting window boundary for all timezone calculations
- **Given** user has admin flag, **When** logged in, **Then** Admin menu item is visible and admin page is accessible

### US-3: Admin — Submission Monitor & Export
**As an** admin user  
**I can** monitor live submissions and export photos + descriptions per photographer  
**So that** I can oversee the event and produce per-photographer packages at close

**Acceptance Criteria**:
- **Given** admin visits submissions view, **When** photos are posted, **Then** admin sees all submissions in real time
- **Given** event ends, **When** admin triggers export, **Then** a downloadable archive is produced per photographer containing their photos and description text

### US-4: Photographer — Photo Posting
**As a** validated photographer  
**I can** post photos with description text during my local event day  
**So that** my contributions appear on the shared home page

**Acceptance Criteria**:
- **Given** it is the event date in the photographer's timezone, **When** they submit a photo + description, **Then** photo is stored in a per-user folder and appears on the home page
- **Given** it is past midnight in the photographer's local timezone, **When** they attempt to post, **Then** submission is rejected with an appropriate message
- **Given** a photo is posted, **When** the description is long, **Then** full text is stored and displayed

### US-5: Home Page — Live Feed
**As a** visitor  
**I can** view all photos in reverse chronological order on the home page  
**So that** I see the event stream as it unfolds

**Acceptance Criteria**:
- **Given** home page is open, **When** a new photo is posted by any photographer, **Then** the page auto-refreshes within 1 minute and displays it
- **Given** multiple photographers post, **When** home page loads, **Then** all photos are interleaved in reverse post time order

### US-6: Photographer Index
**As a** visitor  
**I can** browse an alphabetical index of all participants  
**So that** I can navigate to an individual photographer's page

**Acceptance Criteria**:
- **Given** index page is visited, **When** it loads, **Then** all validated participants are listed A–Z with links to their individual pages
- **Given** a photographer page is visited, **When** it loads, **Then** that photographer's photos and descriptions are displayed

### US-7: Navigation & Header
**As a** visitor  
**I can** use a consistent header/menu across the site  
**So that** I can navigate between Home, Index, Login, and Admin

**Acceptance Criteria**:
- **Given** any page, **When** it renders, **Then** header contains logo and menu items: Home, Index, Log in
- **Given** logged-in admin user, **When** any page renders, **Then** Admin menu item is also visible

---

## Out of Scope

- Push to remote git repository
- Email delivery infrastructure (assume SMTP config is a given; not provisioned by this feature)
- Password reset flow
- Social sharing
- Multiple events / multi-day support
- Image resizing / CDN
- Comments or reactions on photos

---

## Open Questions

1. **Email transport**: Which SMTP provider/config should the validation email use? Credentials location? (Blocks US-1 and US-2 implementation.)
2. **Captcha pool**: Should the 50 questions be hardcoded in PHP or loaded from DB/config file? Who authors them?
3. **Photo storage limits**: Is there a max file size or accepted MIME type list (JPEG only? PNG? WEBP?)?
4. **Export format**: Should the per-photographer export be a ZIP archive, a folder download, or something else? Should descriptions be bundled as a text file alongside images?
5. **Admin bootstrap**: How is the first admin user created? Seeded via migration script, or a setup page that self-locks after first use?
6. **Posting window edge**: Is the window `event_date 00:00–23:59` in the user's timezone, or is there a grace period (e.g., 15 min past midnight)?
7. **Home page refresh**: Should the 1-minute refresh be a full page reload or a React-driven fetch/re-render of just the photo list?
8. **SQLite concurrency**: High concurrent writes on event day may cause SQLite lock contention. Is WAL mode sufficient, or should we consider a lightweight queue?
9. **Authentication session**: Cookie-based sessions only, or should "remember me" / JWT be considered?
10. **Substack URL**: Is the Substack URL displayed publicly on the photographer's index page / individual page?
