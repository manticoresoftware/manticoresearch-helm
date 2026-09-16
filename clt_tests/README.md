# CLT tests

This directory contains CLT recordings for the Helm chart. The tests run commands inside the `manticoresearch/helm-test-kit:0.0.1` Docker image and talk to a local k3s cluster.

## Local k3s setup

Start or refresh the local k3s container and test-kit image:

```bash
clt_tests/run-local.sh --init
```

`run-local.sh --init` creates:

- `clt_tests/k3s.yaml`: kubeconfig that uses the k3s container IP, so Dockerized CLT commands can reach the local cluster.
- `manticoresearch/helm-test-kit:0.0.1`: Docker image used by CLT.

`clt_tests/k3s.yaml` is written for Docker containers, because CLT executes test commands inside Docker. The init path avoids GNU-specific shell tools and checks cluster readiness from Docker with the same test-kit image used by CLT.

## Running tests locally

Use the local runner from the repository root:

```bash
clt_tests/run-local.sh --init
clt_tests/run-local.sh --list
clt_tests/run-local.sh --test sst-scale-replication --debug
clt_tests/run-local.sh --all
```

The runner expects `../clt/clt` and `clt_tests/k3s.yaml` by default. Override them with `--clt /path/to/clt` and `--kubeconfig /path/to/kubeconfig` if needed. `--init` can be used alone or together with a run command.

To build the local Helm images and import them into the k3s container before running tests:

```bash
clt_tests/run-local.sh --init --build-images --test default-flow --debug
```

You can also build/import images without running CLT:

```bash
clt_tests/build-images-local.sh
```

Both commands use the local-only `ci-local` tag. `run-local.sh` writes a temporary Helm values file and mounts it into CLT, so the installed chart uses the images imported into the local k3s container rather than pulling an image from a registry.

The init block in each test exports:

```bash
KUBECONFIG=/tmp/output/kubeconfig-latest.yaml
```

## CI scenarios

CI runs standalone scenario recordings in three duration-balanced jobs. Do not add CI-only dependencies between separate `.rec` files. Put shared setup in `clt_tests/tests/init/*.recb` helpers and include those helpers from each standalone scenario.

Use `clt_tests/tests/init/install.recb` for Helm installs. Each scenario can write `/tmp/clt-values.yaml` before including it to control chart values while keeping the install step shared.

Scenario filenames are descriptive. CI treats every recording as an exact test and uses the shared CLT timing ledger to assign it to the least-loaded of three jobs. The first successful `master` run uses equal default estimates; later successful `master` runs use observed timings. Pull requests restore the baseline for planning but never publish timings.

Current standalone scenarios:

- `balancer-agent-pconn.rec`, `cross-release-seed-restore-mre.rec`, `default-flow.rec`, `empty-cluster-nodes-recovery.rec`
- `no-balancer-flow.rec`, `pod-labels.rec`, `searchd-extra-args.rec`, `sst-scale-replication.rec`
- `stopwords-flow.rec`, `wordforms-configmap.rec`, `worker-mlock-ipc-lock.rec`, `worker-volume-attributes-class.rec`

## Image handoff

Every pull-request run builds the worker and balancer images with the immutable tag `ci-<head SHA>`. The build job uploads them as one short-lived workflow artifact. Each Kubernetes test runner downloads the artifact, imports both images into its own k3s containerd image store, and supplies an overriding values file with `image.pullPolicy: Never`.

This means CLT always tests the images built from the exact PR revision. It does not log into Docker Hub, push images, or rely on a mutable CI tag, so the same workflow is safe for pull requests from forks.

For local testing, the default tag is `ci-local`. Pass a different tag with `--image-tag` when needed; with `--build-images`, the script builds and imports that exact tag before replaying CLT:

```bash
clt_tests/run-local.sh --build-images --image-tag ci-my-change --test default-flow --debug
```

## Cleanup

CLT recordings wait for their Helm release and worker PVCs to disappear before the next scenario can start. If a run is interrupted, clean up manually:

```bash
KUBECONFIG=clt_tests/k3s.yaml helm uninstall my-helm --wait --timeout 180s || true
KUBECONFIG=clt_tests/k3s.yaml kubectl delete pvc -l app.kubernetes.io/component=worker --wait --timeout=180s || true
```
