#!/bin/bash
# Package deploy.sh with SSH key for distribution to telescope machines
# Run this on the machine that has the SSH key

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PKG_DIR="/tmp/ekos-deploy-package"
KEY_FILE="${HOME}/.ssh/id_rsa_star"
KEY_PUB="${HOME}/.ssh/id_rsa_star.pub"

echo "Creating deployment package..."

# Create package directory
rm -rf "${PKG_DIR}"
mkdir -p "${PKG_DIR}"

# Copy SSH key
if [[ ! -f "${KEY_FILE}" ]]; then
    echo "ERROR: SSH key not found at ${KEY_FILE}"
    exit 1
fi

cp "${KEY_FILE}" "${PKG_DIR}/id_rsa_star"
cp "${KEY_PUB}" "${PKG_DIR}/id_rsa_star.pub"
chmod 600 "${PKG_DIR}/id_rsa_star"
chmod 644 "${PKG_DIR}/id_rsa_star.pub"

# Copy deploy scripts
cp "${SCRIPT_DIR}/deploy.sh" "${PKG_DIR}/"
cp "${SCRIPT_DIR}"/*.sh "${PKG_DIR}/" 2>/dev/null || true
cp "${SCRIPT_DIR}"/*.py "${PKG_DIR}/" 2>/dev/null || true

chmod +x "${PKG_DIR}"/*.sh 2>/dev/null || true
chmod +x "${PKG_DIR}"/*.py 2>/dev/null || true

# Create tarball
cd "${PKG_DIR}"
tar -czf "/tmp/ekos-deploy.tar.gz" *

echo ""
echo "Package created: /tmp/ekos-deploy.tar.gz"
echo ""
echo "To deploy to a telescope machine:"
echo "  scp /tmp/ekos-deploy.tar.gz ds@<telescope-ip>:~"
echo "  ssh ds@<telescope-ip>"
echo "  tar -xzf ekos-deploy.tar.gz"
echo "  ./deploy.sh --machine-id scope01"
echo ""
echo "Or use this one-liner:"
echo "  tar -xzf - -C ~ < ekos-deploy.tar.gz && chmod +x ~/deploy.sh && ~/deploy.sh --machine-id scope01"
