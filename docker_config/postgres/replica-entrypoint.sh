#!/bin/sh
# Entrypoint for the streaming-replication standby. On first start (empty data
# directory) it clones the primary with pg_basebackup; -R writes standby.signal
# + primary_conninfo, so PostgreSQL comes up as a read-only hot standby. On
# subsequent restarts the existing data directory is reused and it just resumes
# streaming.
set -e

DATA=/var/lib/postgresql/data/pgdata
PRIMARY_HOST="${PRIMARY_HOST:-notifications_postgresql}"
PRIMARY_PORT="${PRIMARY_PORT:-5432}"
REPLICATION_USER="${REPLICATION_USER:-replicator}"
REPLICATION_SLOT="${REPLICATION_SLOT:-notifications_replica}"

if [ ! -s "$DATA/PG_VERSION" ]; then
    mkdir -p "$DATA"
    chown -R postgres:postgres "$DATA"

    until gosu postgres pg_basebackup \
        -h "$PRIMARY_HOST" -p "$PRIMARY_PORT" -U "$REPLICATION_USER" \
        -D "$DATA" -Fp -Xs -P -R -C -S "$REPLICATION_SLOT"; do
        echo "primary not ready for replication yet, retrying..."
        sleep 2
    done
fi

chown -R postgres:postgres "$DATA"
chmod 0700 "$DATA"

exec gosu postgres postgres -D "$DATA"
