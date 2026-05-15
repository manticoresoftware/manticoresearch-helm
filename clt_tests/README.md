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
clt_tests/run-local.sh --test 3-sst-scale-replication --debug
clt_tests/run-local.sh --thread 1
clt_tests/run-local.sh --all
```

The runner expects `../clt/clt` and `clt_tests/k3s.yaml` by default. Override them with `--clt /path/to/clt` and `--kubeconfig /path/to/kubeconfig` if needed. `--init` can be used alone or together with a run command.

To build the local Helm images and import them into the k3s container before running tests:

```bash
clt_tests/run-local.sh --init --build-images --test 1-default-flow --debug
```

You can also build/import images without running CLT:

```bash
clt_tests/build-images-local.sh
```

Both commands use `manticoresearch/helm-worker:0.0.0-unstable` and `manticoresearch/helm-balancer:0.0.0-unstable`, matching the test values files.

The init block in each test exports:

```bash
KUBECONFIG=/tmp/output/kubeconfig-latest.yaml
```

## CI scenarios

CI runs standalone scenario recordings in parallel. Do not add CI-only dependencies between separate `.rec` files. Put shared setup in `clt_tests/tests/init/*.recb` helpers and include those helpers from each standalone scenario.

Use `clt_tests/tests/init/install.recb` for Helm installs. Each scenario can write `/tmp/clt-values.yaml` before including it to control chart values while keeping the install step shared.

Scenario filenames must start with `1-`, `2-`, or `3-`. CI uses that prefix to choose which of the three CLT threads runs the test.

Current standalone scenarios:

- `clt_tests/tests/1-default-flow.rec`
- `clt_tests/tests/1-no-balancer-flow.rec`
- `clt_tests/tests/2-stopwords-flow.rec`
- `clt_tests/tests/2-wordforms-configmap.rec`
- `clt_tests/tests/3-sst-scale-replication.rec`

## Image tags

During local development the chart `appVersion` can point to an unpublished release tag, for example `25.0.0-YYYYMMDD`. For this workflow we use the latest CI images tagged `0.0.0-unstable` instead of the chart `appVersion` tag. At the time this guide was added, these images were built from commit `0a7e75999379f0403bd8e01669b906a5cb212089`.

- `manticoresearch/helm-worker:0.0.0-unstable`
- `manticoresearch/helm-balancer:0.0.0-unstable`

For tests with the balancer disabled, set at least:

```bash
--set worker.image.tag=0.0.0-unstable
```

For tests with the balancer enabled, set both:

```bash
--set worker.image.tag=0.0.0-unstable --set balancer.image.tag=0.0.0-unstable
```

## Cleanup

Most CLT recordings uninstall the Helm release and delete worker PVCs at the end. If a run is interrupted, clean up manually:

```bash
KUBECONFIG=clt_tests/k3s.yaml helm uninstall my-helm || true
KUBECONFIG=clt_tests/k3s.yaml kubectl delete pvc -l app.kubernetes.io/component=worker || true
```
