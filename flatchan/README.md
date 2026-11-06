# FlatChan 1.0

Lightweight PHP 8.1+ imageboard inspired by vichan/Tinyboard (Yotsuba B look). **No database**: everything is stored in JSON files (`data/*.json.php`). No Composer, shell access or special extensions needed (GD is optional and only used for thumbnails and reaction resizing).

## Install (InfinityFree / any PHP host / VPS)
1. Upload everything to your web root (e.g. `htdocs/`) by FTP.
2. Make sure the folder is writable (755, or 775 if the host requires it).
3. Open `https://your-site/install.php` and press **Install**.
4. Log in at `/login.php` with `admin` / `password`. You are forced to pick a new password immediately.
5. Press **Delete install.php** on the installer page (or delete it by FTP).

Only `/b/` exists after install. No threads, replies, example users or other boards are created.
Apache: `.htaccess` is written automatically (custom 404, no directory listing, `data/` blocked). On nginx, every JSON file also starts with a PHP guard line (`<?php exit;?>`) so it is never readable as text.

## File tree
```
index.php      home: About / News / Boards tables        board.php   board index, threads, posting, reports, mod actions
login.php      staff login (username + password)          dashboard.php  all administration
banned.php     ban notice + ban check                     apl.php     janitor applications
rules.php      rules table                                page.php    router for registered custom pages
404.php        custom 404                                 captcha.php SVG CAPTCHA generator
install.php    installer                                  lib.php     core library (storage, auth, CSRF, formatting, extension API)
assets/        base.css, app.js, banners/ banimgs/ reactions/ icons/ sprites/
themes/        one CSS file per theme (yotsuba-b.css is the default)
extensions/    optional PHP extensions (enable in Dashboard > Extensions)
data/ uploads/ thumbs/    created by the installer
```

## Roles
| Role | Can |
|---|---|
| Janitor | delete posts, delete by IP, ban, approve/reject media, approve news |
| Moderator | + manage reports, pin/sticky/lock/archive/bump threads |
| Admin | + boards, users, applications, site settings, rules, themes, assets, extensions |

Staff can post with their identity (checkbox "Show as ..."): bold red `admin ## Admin`, bold purple `## Mod`, bold orange `## Janitor`.
News approval is allowed for janitor and above (section 4 of the spec); reports are mod and above (section 7).

## Features at a glance
- **Posting**: threads, replies, images (JPG/PNG/GIF/WebP), video (MP4/WebM), audio (MP3/OGG); files are validated by content, not extension. Formatting: `**bold**`, `''italic''`, `__underline__`, `~~strike~~`, `[spoiler]..[/spoiler]`, `[code]..[/code]`, `>greentext`, `>>123` quotes, `:reaction:` images, tripcodes (`name#secret`, `name##secret`), email field keywords `sage`, `supersage`, `nonoko`. Links to YouTube/Vimeo/direct media are embedded.
- **Threads**: pin, sticky, lock, archive (auto-archive when a board exceeds its thread limit), bump. Icons are customizable (Assets & icons).
- **News**: create a board whose URI matches *News board URI* (default `news`). Threads show in the home news table only after approval.
- **CAPTCHA**: on by default for the post and login forms only. Types: SVG text, click minigame (no typing), plus anything registered by extensions. Staff skip the post CAPTCHA.
- **Themes**: dropdown on every page. Admins install/hide/remove/default themes (each is `themes/NAME.css`). Visitors can write and save their own CSS (browser local storage) via *Custom CSS...*.
- **Bans**: posting while banned redirects to `banned.php` (reason, scope, duration, expiry; optional random ban images; temporary or permanent; supports `1.2.3.*` wildcards).
- **Applications**: `apl.php`. Applicants get a private code. When an admin approves, a random password (generated and sealed at submit time, encrypted with a key derived from the code) can only be opened by the applicant, once. Admins never see it.
- **Security**: CSRF tokens, `password_hash`, output escaping, upload sniffing, honeypot, rate limiting (posts, reports, logins, applications), session regeneration on login, locked read-modify-write with atomic file replacement and rolling backups in `data/backup/`.
- No `alert/confirm/prompt`: confirmations use an in-page modal.

## Extensions
Put a file in `extensions/`, with a header comment, then enable it in Dashboard > Extensions:
```php
<?php
/*
Name: My extension
Desc: One-line description.
*/
on('before_post', fn($post,$board)=>str_contains($post['body'],'spam') ? 'No spam.' : $post); // return string = reject
reg_page('hello','Hello',function(){ echo '<p>Hello!</p>'; });            // page.php?p=hello, added to the nav
reg_captcha('mine','My captcha',fn($ctx)=>'...html with input name="cap"...',fn($ctx,$input)=>true);
reg_tab('mytab','My tab','mod',fn()=>print('<p>panel</p>'),fn($act)=>flash('saved')); // dashboard section
```
Hooks: `head`, `foot` (append HTML), `post_form` (extra form rows), `before_post`, `after_post`, `media_pending` (filter; true hides media until approved). Helpers: `db()/dbu()` storage, `cfg()/setcfg()`, `can('janitor|mod|admin')`, `e()`, `csrff()`.

Bundled: **Media approval** (hides uploads and embeds until a janitor+ approves; configurable placeholder/disapproval sprite; works with images, GIFs, video, audio, embeds), **CAPTCHA - simple math**, **Statistics page**.

## Backups / moving
Copy the whole folder. Latest and 7 daily backups of each data file are in `data/backup/`.
