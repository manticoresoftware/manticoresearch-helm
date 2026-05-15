#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  clt_tests/run-local.sh --init
  clt_tests/run-local.sh --list
  clt_tests/run-local.sh --all
  clt_tests/run-local.sh --thread 1
  clt_tests/run-local.sh --test 3-sst-scale-replication
  clt_tests/run-local.sh --test clt_tests/tests/3-sst-scale-replication.rec

Options:
  --init          Recreate local k3s and build the CLT test-kit image.
  --debug         Pass -d to CLT.
  --kubeconfig   Kubeconfig to mount. Default: clt_tests/k3s.yaml
  --clt          Path to CLT binary. Default: ../clt/clt
  --image        Test-kit image. Default: manticoresearch/helm-test-kit:0.0.1
  --build-images Build and import local worker/balancer images before running.
  --k3s-container k3s container name for image import. Default: k3s
USAGE
}

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
tests_dir="$repo_root/clt_tests/tests"
kubeconfig="$repo_root/clt_tests/k3s.yaml"
clt_bin="$repo_root/../clt/clt"
image="manticoresearch/helm-test-kit:0.0.1"
k3s_container="k3s"
mode=""
selector=""
debug=0
build_images=0
init_cluster=0

require_value() {
  if [[ $# -lt 2 || -z "$2" ]]; then
    echo "$1 expects a value" >&2
    exit 2
  fi
}

stop_and_remove_container() {
  local container="$1"
  docker stop "$container" >/dev/null 2>&1 || true
  docker rm "$container" >/dev/null 2>&1 || true
}

wait_for_file() {
  local path="$1"
  local timeout_seconds="$2"
  local elapsed=0

  while [[ ! -s "$path" ]]; do
    if [[ "$elapsed" -ge "$timeout_seconds" ]]; then
      echo "Timed out waiting for $path" >&2
      return 1
    fi
    sleep 1
    elapsed=$((elapsed + 1))
  done
}

rewrite_kubeconfig_for_container() {
  local kubeconfig_file="$1"
  local host_ip="$2"
  local contents

  contents="$(< "$kubeconfig_file")"
  printf '%s' "${contents//127.0.0.1/$host_ip}" > "$kubeconfig_file"
}

wait_for_k3s_ready() {
  local kubeconfig_file="$1"

  docker run --rm \
    -v "$kubeconfig_file:/tmp/output/kubeconfig-latest.yaml" \
    "$image" \
    sh -lc 'chmod 0400 /tmp/output/kubeconfig-latest.yaml; export KUBECONFIG=/tmp/output/kubeconfig-latest.yaml; i=0; until kubectl get nodes --no-headers 2>/dev/null | grep -q " Ready "; do i=$((i + 1)); if [ "$i" -ge 60 ]; then kubectl get nodes 2>/dev/null || true; exit 1; fi; sleep 2; done; kubectl get nodes'
}

init_local_cluster() {
  local clt_dir="$repo_root/clt_tests"
  local local_kubeconfig="$clt_dir/k3s.yaml"
  local k3s_ip

  stop_and_remove_container "$k3s_container"
  stop_and_remove_container kubectl

  : > "$local_kubeconfig"

  docker run -d \
    --name "$k3s_container" \
    -v "$local_kubeconfig:/etc/rancher/k3s/k3s.yaml" \
    -p 6443:6443 \
    --privileged \
    rancher/k3s server >/dev/null

  wait_for_file "$local_kubeconfig" 60

  echo "Building $image"
  docker build -t "$image" "$clt_dir"

  k3s_ip="$(docker inspect "$k3s_container" -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}')"
  if [[ -z "$k3s_ip" ]]; then
    echo "Could not detect k3s container IP" >&2
    exit 1
  fi

  rewrite_kubeconfig_for_container "$local_kubeconfig" "$k3s_ip"
  chmod 0777 "$local_kubeconfig"

  echo "Waiting for k3s to be ready"
  wait_for_k3s_ready "$local_kubeconfig"

  echo "k3s kubeconfig: $local_kubeconfig"
  echo "k3s container IP: $k3s_ip"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --init)
      init_cluster=1
      shift
      ;;
    --list)
      mode="list"
      shift
      ;;
    --all)
      mode="all"
      shift
      ;;
    --thread)
      require_value "$1" "${2:-}"
      mode="thread"
      selector="$2"
      shift 2
      ;;
    --test)
      require_value "$1" "${2:-}"
      mode="test"
      selector="$2"
      shift 2
      ;;
    --debug)
      debug=1
      shift
      ;;
    --kubeconfig)
      require_value "$1" "${2:-}"
      kubeconfig="$2"
      shift 2
      ;;
    --clt)
      require_value "$1" "${2:-}"
      clt_bin="$2"
      shift 2
      ;;
    --image)
      require_value "$1" "${2:-}"
      image="$2"
      shift 2
      ;;
    --build-images)
      build_images=1
      shift
      ;;
    --k3s-container)
      require_value "$1" "${2:-}"
      k3s_container="$2"
      shift 2
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

if [[ "$init_cluster" -eq 1 ]]; then
  init_local_cluster
fi

if [[ -z "$mode" ]]; then
  if [[ "$init_cluster" -eq 1 ]]; then
    exit 0
  fi
  usage >&2
  exit 2
fi

tests=()
while IFS= read -r test; do
  tests+=("$test")
done < <(find "$tests_dir" -maxdepth 1 -type f -name '[1-3]-*.rec' | sort)

if [[ "$mode" == "list" ]]; then
  for test in "${tests[@]}"; do
    rel="${test#$repo_root/}"
    thread="$(basename "$test" | cut -d- -f1)"
    printf 'thread %s  %s\n' "$thread" "$rel"
  done
  exit 0
fi

if [[ ! -x "$clt_bin" ]]; then
  echo "CLT binary not found or not executable: $clt_bin" >&2
  echo "Clone/build CLT next to this repo or pass --clt /path/to/clt." >&2
  exit 1
fi

if [[ ! -f "$kubeconfig" ]]; then
  echo "Kubeconfig not found: $kubeconfig" >&2
  echo "Run: clt_tests/run-local.sh --init" >&2
  exit 1
fi

if [[ "$build_images" -eq 1 ]]; then
  "$repo_root/clt_tests/build-images-local.sh" --k3s-container "$k3s_container"
fi

selected=()
case "$mode" in
  all)
    selected=("${tests[@]}")
    ;;
  thread)
    if [[ ! "$selector" =~ ^[1-3]$ ]]; then
      echo "--thread expects 1, 2, or 3" >&2
      exit 2
    fi
    for test in "${tests[@]}"; do
      if [[ "$(basename "$test")" == "$selector"-* ]]; then
        selected+=("$test")
      fi
    done
    ;;
  test)
    name="$selector"
    name="${name%.rec}"
    name="${name##*/}"
    candidate="$tests_dir/$name.rec"
    if [[ ! -f "$candidate" ]]; then
      echo "Test not found: $selector" >&2
      exit 1
    fi
    selected=("$candidate")
    ;;
esac

if [[ "${#selected[@]}" -eq 0 ]]; then
  echo "No tests selected." >&2
  exit 1
fi

clt_args=(test)
if [[ "$debug" -eq 1 ]]; then
  clt_args+=(-d)
fi

run_args="-e TELEMETRY=0 --net=host -v ${kubeconfig}:/tmp/output/kubeconfig-latest.yaml -v ${repo_root}/charts/:/.clt/charts/"
should_exit=0

for test in "${selected[@]}"; do
  rel="${test#$repo_root/}"
  rep_file="${test%.rec}.rep"
  cmp_file="${test%.rec}.cmp"

  echo "Running $rel"
  if CLT_RUN_ARGS="$run_args" "$clt_bin" "${clt_args[@]}" -t "$rel" "$image"; then
    exit_code=0
  else
    exit_code=$?
    should_exit=1
  fi

  if [[ -f "$rep_file" ]]; then
    echo
    echo "CLT replay output: ${rep_file#$repo_root/}"
    cat "$rep_file"
  fi

  if [[ "$exit_code" -ne 0 ]]; then
    echo
    echo "Test failed with exit code: $exit_code"
    if [[ -f "$cmp_file" ]]; then
      echo "CLT-CMP diff output: ${cmp_file#$repo_root/}"
      cat "$cmp_file"
    else
      echo "CLT-CMP diff output was not produced: ${cmp_file#$repo_root/}"
    fi
  fi

  echo "---"
done

exit "$should_exit"
