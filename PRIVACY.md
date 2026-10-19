# Privacy Policy

cfdi-autofile is a personal tool that runs only on the machine where it is installed. There is no hosted service, no analytics, and no third party other than Google Drive, which you connect yourself.

## What the app accesses

When you connect Google Drive, the app asks for the `https://www.googleapis.com/auth/drive` scope and uses it only to:

- read the name of the connected account, so the settings screen can show which account you authorized;
- find or create the destination folders for your CFDIs;
- upload your renamed CFDI files (PDF and XML), or replace a copy it uploaded before.

The app does not read, change, or delete any other file in your Drive.

## What the app stores

Everything is stored in the local database on your machine:

- the Google refresh token, encrypted with the app's `APP_KEY`;
- the email address of the connected Google account;
- the CFDIs you process and the remote IDs of the copies uploaded to Drive.

Access tokens are never stored. The app requests a new one for each run.

## Sharing

The app sends your files only to your own Google Drive. It does not send any data to the developer or to anyone else.

## Revoking access

- In the app, open the settings screen and disconnect Google Drive. This deletes the stored token and account address.
- In your Google account, remove the app at https://myaccount.google.com/permissions.

## Contact

Open an issue at https://github.com/roelmagdaleno/cfdi-autofile/issues.
