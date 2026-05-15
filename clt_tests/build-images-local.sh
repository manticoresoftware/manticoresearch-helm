#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  clt_tests/build-images-local.sh [options]

Builds the Helm worker and balancer images with the local CI tag and imports
them into the local k3s container created by clt_tests/run-local.sh --init.

Options:
  --tag             Image tag. Default: 0.0.0-unstable
  --k3s-container   k3s container name. Default: k3s
  --no-import       Build Docker images but do not import them into k3s.
USAGE
}

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
tag="0.0.0-unstable"
k3s_container="k3s"
do_import=1

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tag)
      tag="$2"
      shift 2
      ;;
    --k3s-container)
      k3s_container="$2"
      shift 2
      ;;
    --no-import)
      do_import=0
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

worker_image="manticoresearch/helm-worker:${tag}"
balancer_image="manticoresearch/helm-balancer:${tag}"

echo "Building $worker_image"
docker build -t "$worker_image" "$repo_root/sources/manticore-worker"

echo "Building $balancer_image"
docker build -t "$balancer_image" "$repo_root/sources/manticore-balancer"

if [[ "$do_import" -eq 0 ]]; then
  exit 0
fi

if ! docker inspect "$k3s_container" >/dev/null 2>&1; then
  echo "k3s container not found: $k3s_container" >&2
  echo "Run: clt_tests/run-local.sh --init" >&2
  exit 1
fi

echo "Importing images into k3s container: $k3s_container"
docker save "$worker_image" "$balancer_image" | docker exec -i "$k3s_container" ctr -n k8s.io images import -

echo "Imported:"
docker exec "$k3s_container" ctr -n k8s.io images ls | grep -E "manticoresearch/helm-(worker|balancer):${tag}" || true
