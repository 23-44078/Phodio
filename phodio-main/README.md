# phodio-main/ — mirror of the app

This folder is an exact copy of the application at the repository root
(`../api`, `../vercel.json`, ...).

It exists only because this Vercel project's **Root Directory** setting was
pointed at `phodio-main` in the dashboard, and dashboard settings cannot be
changed from Git. With this folder present, the deployment works no matter
whether Root Directory is `./` or `phodio-main`.

If you ever change Root Directory back to `./` (recommended), you can delete
this folder. When editing the app, always edit the copies at the repository
root, then refresh this mirror (or remove it once Root Directory is `./`).
