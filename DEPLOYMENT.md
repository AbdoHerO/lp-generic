# Deploying lp_tifaw to tujjar.store with CloudForge

The repository now carries everything the VPS needs. What remains is entering
the pipeline in CloudForge once; after that, every deploy is **Jenkins Pipelines
→ Run pipeline**.

| Setting                        | Value                                       |
| ------------------------------ | ------------------------------------------- |
| Domain                         | `tujjar.store`                              |
| Application port (VPS loopback) | `8090`                                      |
| Repository                     | `https://github.com/AbdoHerO/lp-generic.git` (public) |
| Branch                         | `main`                                      |
| Jenkinsfile path               | `Jenkinsfile`                               |
| Compose project                | `lp-tifaw`                                  |

## What the stack looks like on the VPS

```
Internet → Cloudflare (proxied)
         → OCI security list  :443/:80
         → host firewall      :443/:80
         → Nginx  (CloudForge-managed site for tujjar.store)
         → 127.0.0.1:8090
         → app container   php:8.2-apache
         → db container    mysql:8.0        (private, no published port)
```

Only Nginx is reachable from the internet. The application port is bound to
`127.0.0.1`, and MySQL publishes nothing at all — it exists only on the compose
bridge network.

---

## 0. Prerequisites on the VPS

Do these in **Ansible** if they are not already done. Skip any that are.

1. **Docker Engine** profile. In the *Docker users* field enter both your SSH
   user and `jenkins`, comma separated. Jenkins runs `docker compose` as itself;
   without group membership every build fails on the first `docker` call.
2. **Jenkins** profile, then **Verify Jenkins**.
3. **Nginx** profile — CloudForge writes the site file, but the package has to
   be there first.

Confirm ports **80** and **443** are open in both firewalls. **VPS Runtime →
Connectivity** answers this for the host and the OCI security list in one place;
the **Firewall** page edits the OCI side. Port 8090 must stay **closed** — it is
loopback-only by design and no firewall rule can or should expose it.

## 1. Store the environment file

**Secrets → Add credential → Deployment Environment File**

- **Name:** `lp-tifaw env production`
- **Filename:** `.env.production`
- **Content:** use the file picker to select the generated file, or paste it.

It carries the database passwords and the admin password. `.env.example` in this
repository documents every key. Nothing in it may read `CHANGE_ME_*` — CloudForge
blocks deployment while a placeholder remains, and reports only the variable
names, never the values.

You can edit this credential later from **Secrets** without touching Jenkins:
every build refreshes the folder-scoped Jenkins secret from it.

## 2. Create the pipeline

**Jenkins Pipelines → New pipeline**

| Field                                  | Value                                            |
| -------------------------------------- | ------------------------------------------------ |
| VPS target                             | your HanoutPlus server                           |
| Jenkins credential                     | your stored Jenkins credential                    |
| Name                                   | `lp-tifaw`                                       |
| Definition                             | **Jenkinsfile from Git**                         |
| Repository visibility                  | **Public repository**                            |
| Repository URL                         | `https://github.com/AbdoHerO/lp-generic.git`     |
| Branch / ref                           | `main`                                           |
| Jenkinsfile path                       | `Jenkinsfile`                                    |
| Encrypted deployment environment file  | `lp-tifaw env production`                        |
| Configure application domain           | **on**                                           |
| Domain                                 | `tujjar.store`                                   |
| Application port                       | `8090`                                           |
| Additional application routes          | none                                             |

Leave build parameters empty. `HOST_PORT` and `CLOUDFORGE_ENV_CREDENTIAL_ID` are
added and owned by CloudForge; `HOST_PORT` is read-only in the run form on
purpose, because it must always equal the port the generated Nginx site proxies
to. To change the port later, change **Application port** here — never in Jenkins.

**Save to Jenkins.** This creates the folder and job, creates or updates the
Cloudflare DNS record, and writes the Nginx site. It does **not** start a build.

> **If saving fails on DNS:** an `A` record for `tujjar.store` that CloudForge
> did not create and that points somewhere other than this VPS is refused rather
> than repointed — the error names both addresses. Either point that record at
> the VPS in Cloudflare yourself or delete it, then save again. A record already
> pointing at the VPS is left untouched and saving proceeds.

## 3. First run

**Run pipeline.**

The first build takes a few minutes: Docker pulls `php:8.2-apache` and
`mysql:8.0`, then the entrypoint imports `sql/schema.sql` and `sql/seed.sql` into
the empty database and sets the admin password from the environment file.

Afterwards click **Status / sync parameters** once. Jenkins only learns a
declarative pipeline's parameters after it has evaluated the `Jenkinsfile` during
a build, so this is what populates the run form.

The build fails rather than silently half-deploying if the app does not answer
`ok` on `http://127.0.0.1:8090/health.php` within two minutes; the console then
prints the container logs.

## 4. Nginx upload limit

**Nginx → Sites → `tujjar.store` → edit → body size: `12M`** → validate and apply.

The default is 1 MB. The admin product editor accepts images up to 5 MB, and
Nginx would reject them before PHP ever sees them — which looks to the admin like
the upload silently did nothing. PHP is already configured to match
(`post_max_size = 12M`).

## 5. Certificate

**SSL & Domains**, with `tujjar.store` proxied in Cloudflare:

- **Cloudflare Origin CA** — the right choice here, since the record is proxied.
  Select the Cloudflare credential, verify DNS, then **Issue certificate**.
  CloudForge generates the key and CSR on the VPS, installs the certificate,
  reloads Nginx through its validate-and-rollback transaction, and switches the
  zone to Full (strict).

  The token needs **Zone → SSL and Certificates → Edit** in addition to the DNS
  and Zone Settings permissions it already has.

- **Let's Encrypt** is the alternative if you ever turn the orange cloud off.
  It requires port 80 reachable directly from the internet.

Origin CA certificates are trusted by Cloudflare, not by browsers connecting
straight to the VPS IP. Keep the record proxied.

## 6. Verify

1. `https://tujjar.store` serves the storefront.
2. `https://tujjar.store/admin/login.php` — sign in with `admin` and the
   `ADMIN_PASSWORD` from the environment file.
3. Change the password in **Settings** if you want one you chose yourself.
4. Submit a test order and confirm it appears under **Leads**, with a real
   visitor IP rather than a Docker address.
5. Upload a product image over 2 MB to confirm step 4 above took effect.

---

## Operating it

Commands run on the VPS, from anywhere — `-p lp-tifaw` makes them
directory-independent.

```sh
docker compose -p lp-tifaw ps
docker compose -p lp-tifaw logs -f app
docker compose -p lp-tifaw restart app
```

**Back up before anything destructive.** `uploads/` is gitignored, so the volume
is the only copy of every product image:

```sh
docker run --rm -v lp-tifaw_uploads:/src -v "$PWD":/out alpine \
  tar czf /out/lp-tifaw-uploads-$(date +%F).tar.gz -C /src .

docker exec lp-tifaw-db-1 sh -c \
  'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction lp_tifaw' \
  > lp-tifaw-db-$(date +%F).sql
```

**Rolling back** is re-running the pipeline against an earlier commit: change the
branch/ref to that commit in CloudForge and run. Images are tagged `lp-tifaw:b<build>`,
so `docker compose -p lp-tifaw up -d` with `IMAGE_TAG=b12` also works for an
immediate revert without a rebuild.

**Changing a secret:** edit the credential in **Secrets**, then run the pipeline.
Do not edit anything in the Jenkins UI — CloudForge reasserts the values it owns
on every read, so a Jenkins-side edit is silently reverted rather than honoured.

## Design notes

- **`config/config.prod.php` is generated at container start**, by the entrypoint,
  from environment variables. `config/config.php` already prefers that file, so
  no committed application source changed to make this deployable. It is written
  at runtime rather than baked in, so no image layer carries a password.
- **The seeded `admin`/`admin123` account** is overwritten on every start from
  `ADMIN_PASSWORD`. It is verified before being rewritten, so an unchanged
  password does not rotate the hash on each restart.
- **Migrations run in the entrypoint**, not on the first HTTP request. The app
  would have done it either way (`_auto_migrate` in `config/database.php`), but
  doing it before Apache starts means the health gate tests a migrated database
  instead of racing one.
- **No CloudForge ownership labels are stamped** on these containers. VPS Runtime
  will correctly report the stack as unmanaged; adopting it from that page is the
  documented, reversible way to change that, and forging the labels here would
  claim an authority nothing granted.
