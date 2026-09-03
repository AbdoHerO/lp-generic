// lp_tifaw — deployment pipeline, run by Jenkins on the VPS.
//
// Everything deployed comes from git. The workspace is a clean `checkout scm`,
// the image is built from that checkout, and the SQL the database work uses is
// sql/*.sql out of the same commit — there is no manual step and no file that
// reaches production any other way. The only inputs from outside the repository
// are the encrypted environment file and the build parameters below.
//
// Contract with CloudForge (see docs/JENKINS-PIPELINES.md in the CloudForge
// repository):
//
//   HOST_PORT                    the loopback port the managed Nginx site
//                                proxies to. CloudForge owns it: it is
//                                read-only in the run form, reasserted on every
//                                status read, and a value supplied at trigger
//                                time is overridden rather than honoured. To
//                                change it, change the pipeline's application
//                                port in CloudForge.
//   CLOUDFORGE_ENV_CREDENTIAL_ID the id of a folder-scoped Jenkins secret-text
//                                credential holding the base64 of the
//                                environment file. CloudForge refreshes it from
//                                the encrypted credential on every build, so
//                                editing the credential needs no Jenkins work.
//
// Both defaults below matter on the very first build: Jenkins only learns a
// declarative pipeline's parameters after it has evaluated this file once, so
// until then it queues the job with whatever defaults are written here.

pipeline {
    agent any

    // Deliberately no timestamps(): that needs the Timestamper plugin, which is
    // not among the plugins CloudForge requires, and a missing plugin fails the
    // job before a single stage runs.
    options {
        // Two builds racing `compose up` on one host would fight over the same
        // container names and the same published port.
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '20'))
        timeout(time: 25, unit: 'MINUTES')
    }

    parameters {
        choice(
            name: 'DEPLOY_MODE',
            choices: ['deploy', 'deploy-with-seed', 'deploy-fresh', 'rebuild-no-cache', 'restart', 'rollback'],
            description: '''What this run should do. The database is left alone unless the mode says otherwise.

• deploy — the normal one. Build the image from this commit, start it, apply any
  pending schema migrations. Products, orders and settings are untouched.

• deploy-with-seed — deploy, then import sql/seed.sql. Refuses if the catalogue
  already has products, so it cannot duplicate a live store. For first launches
  and rebuilt staging environments.

• deploy-fresh — DESTRUCTIVE. Deploy, then DROP every table and rebuild from
  sql/schema.sql + sql/seed.sql. Every product, order and setting is deleted.
  Requires CONFIRM_DESTRUCTIVE = FRESH.

• rebuild-no-cache — same as deploy but rebuilds every image layer. Use when a
  base-image or dependency change is not being picked up.

• restart — no build. Recreate the containers from the image already on the host.
  For an env-file change or a stuck container.

• rollback — no build. Start a previously built image; set ROLLBACK_TAG.'''
        )
        string(
            name: 'ROLLBACK_TAG',
            defaultValue: '',
            description: 'rollback mode only: the image tag to deploy, e.g. b12. Must already exist on the VPS (docker images lp-tifaw).'
        )
        booleanParam(
            name: 'BACKUP_DB',
            defaultValue: true,
            description: 'Dump the database before touching it. Always forced on for deploy-fresh. Dumps contain customer PII and are kept on the VPS only — they are never archived as build artifacts.'
        )
        string(
            name: 'CONFIRM_DESTRUCTIVE',
            defaultValue: '',
            description: 'Type FRESH to authorise deploy-fresh. Ignored by every other mode.'
        )
        string(
            name: 'HOST_PORT',
            defaultValue: '8090',
            description: 'VPS loopback port. Managed by CloudForge — set it there, not here.'
        )
        string(
            name: 'CLOUDFORGE_ENV_CREDENTIAL_ID',
            defaultValue: params.CLOUDFORGE_ENV_CREDENTIAL_ID ?: '',
            description: 'Managed by CloudForge.'
        )
    }

    environment {
        // Compose reads this natively. It pins the project name regardless of
        // which directory the build runs from, so a wiped workspace still finds
        // the same containers and — more importantly — the same volumes.
        COMPOSE_PROJECT_NAME = 'lp-tifaw'
        IMAGE_TAG = "b${BUILD_NUMBER}"
        HEALTH_URL = "http://127.0.0.1:${params.HOST_PORT}/health.php"

        // Backups live next to the workspace rather than inside it: a workspace
        // wipe must not take the only copy of a pre-deploy dump with it.
        BACKUP_DIR = "${JENKINS_HOME}/backups/lp-tifaw"
    }

    stages {

        stage('Checkout') {
            steps {
                checkout scm
                sh 'git --no-pager log -1 --pretty="%h %an %s"'
                script {
                    // The deployed artefact is the commit, so name it in the
                    // build. A rollback then reads as a tag, not a mystery.
                    def sha = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    currentBuild.displayName = "#${BUILD_NUMBER} ${params.DEPLOY_MODE} ${sha}"
                }
            }
        }

        stage('Preflight') {
            steps {
                sh '''
                    set -eu
                    docker version >/dev/null
                    docker compose version >/dev/null
                '''
                script {
                    if (!params.CLOUDFORGE_ENV_CREDENTIAL_ID?.trim()) {
                        error('''No environment credential.

Attach an encrypted deployment environment file to this pipeline in CloudForge
(Jenkins Pipelines -> edit -> "Encrypted deployment environment file"), save it,
and run the pipeline from CloudForge once. That managed run installs the secret
into this Jenkins folder and supplies its id; later runs reuse it automatically.''')
                    }

                    // Every guard that can be checked before anything is built
                    // is checked here, so a misconfigured run costs no time and
                    // leaves the running site untouched.
                    if (params.DEPLOY_MODE == 'deploy-fresh' && params.CONFIRM_DESTRUCTIVE?.trim() != 'FRESH') {
                        error('''deploy-fresh DROPS every table: all products, all orders, all settings.

Set CONFIRM_DESTRUCTIVE to exactly

    FRESH

and run again. If you only wanted to publish new code, use "deploy" — it applies
schema migrations without touching your data.''')
                    }
                    if (params.DEPLOY_MODE == 'rollback' && !params.ROLLBACK_TAG?.trim()) {
                        error('rollback needs ROLLBACK_TAG (e.g. b12). List what is on the host with: docker images lp-tifaw')
                    }

                    // The whole point of the mode is that the deployed files are
                    // whatever this commit holds, so verify the pieces the
                    // database stages depend on actually arrived in the checkout.
                    sh '''
                        set -eu
                        missing=""
                        for f in sql/schema.sql sql/seed.sql bin/db.php config/migrations.php \
                                 Dockerfile docker-compose.yml docker/entrypoint.sh; do
                            [ -f "$f" ] || missing="$missing $f"
                        done
                        if [ -n "$missing" ]; then
                            echo "These files are missing from the checkout:$missing" >&2
                            echo "They are required to deploy. Check .gitignore — everything the" >&2
                            echo "deployment needs must be committed." >&2
                            exit 1
                        fi
                        echo "Checkout complete: $(git ls-files | wc -l) tracked file(s)."
                    '''
                }
            }
        }

        stage('Environment') {
            steps {
                // The credential holds base64 of the whole file, so a value can
                // contain '=' or newlines without any escaping question.
                withCredentials([string(
                    credentialsId: params.CLOUDFORGE_ENV_CREDENTIAL_ID,
                    variable: 'CF_ENV_B64'
                )]) {
                    sh '''
                        set -eu
                        # Written before the file exists, so it is never briefly
                        # world-readable on a shared build host.
                        umask 077
                        printf '%s' "$CF_ENV_B64" | base64 -d > .env

                        # Fail here, with a clear message, rather than inside a
                        # compose variable-substitution error.
                        for key in DB_PASSWORD DB_ROOT_PASSWORD DB_NAME DB_USER; do
                            grep -qE "^${key}=." .env || {
                                echo "Environment file is missing ${key}." >&2
                                exit 1
                            }
                        done
                        echo "Environment file loaded ($(grep -c '=' .env) values)."
                    '''
                }
            }
        }

        stage('Backup') {
            // Before the new image starts, so the dump reflects the schema the
            // currently running code wrote. Skipped when there is nothing to
            // back up yet (a first deploy has no stack running).
            when {
                expression { params.BACKUP_DB || params.DEPLOY_MODE == 'deploy-fresh' }
            }
            steps {
                sh '''
                    set -eu
                    if ! docker compose ps --status running --services 2>/dev/null | grep -qx db; then
                        echo "No running database container — nothing to back up (first deploy?)."
                        exit 0
                    fi

                    umask 077
                    mkdir -p "$BACKUP_DIR"
                    stamp=$(date +%Y%m%d-%H%M%S)
                    file="$BACKUP_DIR/db-${stamp}-b${BUILD_NUMBER}.sql"

                    # Dumped from the database container, not the app container.
                    # The app image running at this moment is the OLD one, which
                    # on the very first run of this pipeline predates bin/db.php
                    # — a backup must not depend on the thing being replaced.
                    # mysql:8.0 ships mysqldump and already holds the credentials
                    # in its own environment, so nothing is passed on a command
                    # line where `ps` could read it.
                    #
                    # --no-tablespaces: without the PROCESS privilege mysqldump
                    # 8 fails outright rather than skipping the tablespace query.
                    docker compose exec -T db sh -c \\
                        'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" \\
                             --single-transaction --no-tablespaces --routines \\
                             "$MYSQL_DATABASE"' > "$file"

                    if [ ! -s "$file" ]; then
                        echo "Backup is empty — refusing to continue." >&2
                        rm -f "$file"
                        exit 1
                    fi

                    gzip -f "$file"
                    chmod 600 "${file}.gz"
                    echo "Backup: ${file}.gz ($(du -h "${file}.gz" | cut -f1))"

                    # Keep 14; these contain customer names, phones and addresses,
                    # so they are pruned rather than kept forever on the build host.
                    ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | tail -n +15 | xargs -r rm -f
                '''
            }
        }

        stage('Build image') {
            when {
                expression { params.DEPLOY_MODE in ['deploy', 'deploy-with-seed', 'deploy-fresh', 'rebuild-no-cache'] }
            }
            steps {
                script {
                    // --pull picks up php:8.2-apache security updates; without it
                    // a long-lived host keeps deploying a stale base layer.
                    def noCache = params.DEPLOY_MODE == 'rebuild-no-cache' ? '--no-cache' : ''
                    sh """
                        set -eu
                        docker compose build --pull ${noCache}
                    """
                }
            }
        }

        stage('Deploy') {
            steps {
                script {
                    // rollback and restart deploy an image that already exists on
                    // the host rather than one this build produced, so the tag
                    // Compose uses is resolved per mode.
                    def tag
                    if (params.DEPLOY_MODE == 'rollback') {
                        tag = params.ROLLBACK_TAG.trim()
                    } else if (params.DEPLOY_MODE == 'restart') {
                        // Read the tag off the container running right now, so a
                        // restart after a rollback restarts the rolled-back image
                        // rather than the newest one sitting on the host.
                        tag = sh(
                            script: "docker compose ps -q app 2>/dev/null | head -1 " +
                                    "| xargs -r docker inspect -f '{{.Config.Image}}' | cut -d: -f2",
                            returnStdout: true
                        ).trim()
                        if (!tag) {
                            error('restart: nothing is running, so there is no image to restart. Use "deploy".')
                        }
                    } else {
                        tag = env.IMAGE_TAG
                    }

                    // restart exists to pick up an env-file change or unstick a
                    // container, and a plain `up -d` is a no-op when the config
                    // is unchanged — which would make the mode do nothing at all.
                    def recreate = params.DEPLOY_MODE == 'restart' ? '--force-recreate' : ''

                    sh """
                        set -eu
                        # HOST_PORT and IMAGE_TAG reach Compose through the process
                        # environment (Jenkins exports build parameters), and a shell
                        # variable beats the .env file in Compose's precedence order.
                        # So the CloudForge-owned parameter stays the single source of
                        # truth for the port the Nginx site proxies to.
                        export IMAGE_TAG='${tag}'

                        if ! docker image inspect 'lp-tifaw:${tag}' >/dev/null 2>&1; then
                            echo 'Image lp-tifaw:${tag} does not exist on this host.' >&2
                            echo 'Available:' >&2
                            docker images --format '  {{.Repository}}:{{.Tag}}  {{.CreatedSince}}' lp-tifaw >&2
                            exit 1
                        fi

                        echo "Publishing lp-tifaw:${tag} on 127.0.0.1:\${HOST_PORT}"
                        docker compose up -d --remove-orphans ${recreate}
                        docker compose ps
                    """
                }
            }
        }

        stage('Health gate') {
            steps {
                sh '''
                    set -eu

                    # Probes the published loopback port rather than the
                    # container's own healthcheck: that is the exact address the
                    # managed Nginx site proxies to, so a pass here means the
                    # domain will work, not merely that Apache is up.
                    if command -v curl >/dev/null 2>&1; then
                        fetch() { curl -fsS --max-time 5 "$1" 2>/dev/null; }
                    elif command -v wget >/dev/null 2>&1; then
                        fetch() { wget -qO- --timeout=5 "$1" 2>/dev/null; }
                    else
                        echo "Neither curl nor wget is installed on this VPS." >&2
                        exit 1
                    fi

                    echo "Probing $HEALTH_URL"
                    i=0
                    until fetch "$HEALTH_URL" | grep -qx 'ok'; do
                        i=$((i + 1))
                        if [ "$i" -ge 40 ]; then
                            echo "Application did not become healthy within 2 minutes." >&2
                            exit 1
                        fi
                        sleep 3
                    done
                    echo "Healthy after $((i * 3))s."
                '''
            }
        }

        stage('Database') {
            steps {
                script {
                    // Every mode runs `migrate`, including restart and rollback:
                    // migrations are idempotent, and a rollback to an image that
                    // predates a column is exactly when you want to know the
                    // schema state out loud rather than discover it in a 500.
                    def modeCommand = [
                        'deploy'            : 'php bin/db.php migrate',
                        'rebuild-no-cache'  : 'php bin/db.php migrate',
                        'restart'           : 'php bin/db.php migrate',
                        'rollback'          : 'php bin/db.php migrate',
                        'deploy-with-seed'  : 'php bin/db.php migrate && php bin/db.php seed',
                        'deploy-fresh'      : 'php bin/db.php fresh --force',
                    ][params.DEPLOY_MODE]

                    sh """
                        set -eu
                        echo "Mode ${params.DEPLOY_MODE}: ${modeCommand}"
                        docker compose exec -T app sh -c '${modeCommand}'
                    """

                    // The report every deploy should end with: what schema is
                    // live, and which pixels the landing pages will fire.
                    sh 'docker compose exec -T app php bin/db.php status'

                    // deploy-fresh and deploy-with-seed rewrote tables after the
                    // health gate passed, so prove the site still answers rather
                    // than finding out from a visitor.
                    if (params.DEPLOY_MODE in ['deploy-fresh', 'deploy-with-seed']) {
                        sh '''
                            set -eu
                            if command -v curl >/dev/null 2>&1; then
                                fetch() { curl -fsS --max-time 5 "$1" 2>/dev/null; }
                            else
                                fetch() { wget -qO- --timeout=5 "$1" 2>/dev/null; }
                            fi
                            i=0
                            until fetch "$HEALTH_URL" | grep -qx 'ok'; do
                                i=$((i + 1))
                                [ "$i" -ge 20 ] && { echo "Unhealthy after the database rebuild." >&2; exit 1; }
                                sleep 3
                            done
                            echo "Still healthy after the database work."
                        '''
                    }
                }
            }
        }

        stage('Cleanup') {
            steps {
                // Only dangling layers: a filtered prune here must never remove
                // an image another stack on this VPS is still using, and never
                // an older lp-tifaw tag that a rollback might need.
                sh 'docker image prune -f'
            }
        }
    }

    post {
        failure {
            sh '''
                echo "===== compose ps ====="
                docker compose ps || true
                echo "===== app logs ====="
                docker compose logs --tail=120 app || true
                echo "===== db logs ====="
                docker compose logs --tail=60 db || true
                echo "===== available backups ====="
                ls -1t "$BACKUP_DIR" 2>/dev/null | head -5 || echo "(none)"
            '''
        }
        always {
            // The decoded environment must not survive the build, even when a
            // stage failed before compose ever read it.
            sh 'rm -f .env'
        }
        success {
            echo "Deployed lp-tifaw (${params.DEPLOY_MODE}) on 127.0.0.1:${params.HOST_PORT}"
        }
    }
}
