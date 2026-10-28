#!/usr/bin/env bash
set -e

# Source the scripts in order
source ./scripts/detect_os.sh
source ./scripts/setup_commands.sh
source ./scripts/pre_deploy.sh
source ./scripts/install_deps.sh
source ./scripts/migrate.sh
source ./scripts/optimize.sh

echo "✅ Deployment complete!"
