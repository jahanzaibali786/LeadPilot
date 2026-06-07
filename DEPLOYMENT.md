# Deployment

The production deployment is handled by `.github/workflows/deploy.yml` for `https://crm.verositydigital.com`.

Required GitHub repository secret:

- `SFTP_PASSWORD`: SFTP account password

The workflow already contains the provided SFTP host, port, and username:

- Host: `access-5020464778.webspace-host.com`
- Port: `22`
- Username: `a2901180`

Optional GitHub repository secret:

- `SFTP_REMOTE_DIR`: remote directory for the subdomain. If it is not set, deployment mirrors to `/` like the provided workflow.

The workflow builds Vite assets, runs the Laravel test suite, installs production Composer dependencies, and mirrors the deployable files over SFTP. The server `.env`, storage runtime files, logs, cached views, and tests are not uploaded.

Before the first production run, make sure the server has a valid Laravel `.env`, database credentials, `APP_KEY`, and writable `storage/` plus `bootstrap/cache/` directories.
