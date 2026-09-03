// lp_tifaw — deployment pipeline, run by Jenkins on the VPS.
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
    }

    stages {

        stage('Checkout') {
            steps {
                checkout scm
                sh 'git --no-pager log -1 --pretty="%h %an %s"'
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

        stage('Build image') {
            steps {
                sh '''
                    set -eu
                    # --pull picks up php:8.2-apache security updates; without it
                    # a long-lived host keeps deploying a stale base layer.
                    docker compose build --pull
                '''
            }
        }

        stage('Deploy') {
            steps {
                sh '''
                    set -eu
                    # HOST_PORT and IMAGE_TAG reach Compose through the process
                    # environment (Jenkins exports build parameters), and a shell
                    # variable beats the .env file in Compose's precedence order.
                    # So the CloudForge-owned parameter stays the single source of
                    # truth for the port the Nginx site proxies to.
                    echo "Publishing on 127.0.0.1:${HOST_PORT}"
                    docker compose up -d --remove-orphans
                    docker compose ps
                '''
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

        stage('Cleanup') {
            steps {
                // Only dangling layers: a filtered prune here must never remove
                // an image another stack on this VPS is still using.
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
            '''
        }
        always {
            // The decoded environment must not survive the build, even when a
            // stage failed before compose ever read it.
            sh 'rm -f .env'
        }
        success {
            echo "Deployed lp-tifaw ${IMAGE_TAG} on 127.0.0.1:${params.HOST_PORT}"
        }
    }
}
