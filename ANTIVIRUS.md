# Upload antivirus scanning

Uploaded files are streamed to ClamAV before they are stored or submitted for
approval. If ClamAV reports a detection, an encrypted file, or certain scan
limits, the upload is rejected. If scanning fails, times out, or the daemon is
unavailable, uploads are rejected until it recovers. No scan is infallible:
ClamAV can return clean for content it cannot fully interpret, and the scan
does not replace the existing approval process. Previously stored files are
not scanned by this integration.

## Deployment

Docker Compose starts the official ClamAV 1.4 LTS image with a persistent
signature database and FreshClam's automatic updates. The initial download and
signature loading may take several minutes; PHP remains available, but uploads
fail until the scanner is ready. Allow outbound DNS and HTTPS for signature
updates. The service has a 4 GiB memory limit and two CPUs; ClamAV recommends
at least 3 GiB and preferably 4 GiB of RAM. Keep the image on the latest 1.4
patch and plan an upgrade before that release reaches end of support.
Run `docker compose up -d` to apply the updated configuration; existing PHP
containers must be recreated to join the scanner network. Check service health
after deployment before expecting uploads to succeed.

The scanner is on a separate network shared only with PHP, and port 3310 is
not published. ClamAV's TCP protocol has no authentication or encryption:
restrict network access to trusted application containers. For other
deployments, configure `CLAMAV_HOST`, `CLAMAV_PORT`, and `CLAMAV_TIMEOUT`
(seconds, 1–300) for a trusted clamd server. Defaults are `clamav`, `3310`
and `45`. The external daemon must use equivalent scanning limits, encrypted
file alerts and current signatures. Never expose it to untrusted networks.

The PHP upload and stream limit are 10 MiB. The existing 10 MiB POST limit
also includes multipart overhead. Expanded content has separate ClamAV limits;
`AlertExceedsMax` alerts on MaxFileSize, MaxScanSize and MaxRecursion, but
ClamAV does not guarantee that every parsing or other limit is reported as an
error. Encrypted documents and broken media may be rejected even if benign.

## Monitoring and checks

Check `docker compose ps` for the scanner health status and
`docker compose logs clamav` for signature download, reload, or scan errors.
The health check performs a real scan, not just a TCP ping. Monitor FreshClam
logs and database age separately: health alone does not establish signature
freshness. PHP logs scan failures and detections without file contents or names.
Investigate persistent failures, restore network access or resource limits,
and retry uploads after the service becomes healthy. Docker does not
automatically restart a container solely because it is unhealthy.

After starting the stack, run the real scanner integration test with
`docker compose exec -e CLAMAV_INTEGRATION=1 php php vendor/bin/phpunit tests/Integration/ClamAvIntegrationTest.php`.
It creates temporary clean and EICAR test files inside the PHP container and
does not upload or persist them. Run the normal PHPUnit suite separately.

The optional `tests/Integration/UploadHttpTest.php` exercises multipart uploads
against real PHP and ClamAV services. Set `UPLOAD_TEST_BASE_URL` to a test PHP
server, `UPLOAD_TEST_OUTAGE_URL` to a second test server configured with an
unreachable ClamAV endpoint, and `UPLOAD_TEST_DB_HOST`, `UPLOAD_TEST_DB_NAME`,
`UPLOAD_TEST_DB_USER`, and `UPLOAD_TEST_DB_PASSWORD` to an isolated database
initialized with `init.sql`. Both servers and the test runner must share the
same dedicated, initially empty `UPLOAD_DIR` and database. Do not point this
test at production: it creates users and promotions and deletes its test files.
