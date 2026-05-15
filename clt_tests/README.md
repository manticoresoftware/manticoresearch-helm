# CLT tests

This directory contains CLT recordings for the Helm chart. The tests run commands inside the `manticoresearch/helm-test-kit:0.0.1` Docker image and talk to a local k3s cluster.

## Local k3s setup

Start or refresh the local k3s container and test-kit image:

```bash
cd clt_tests
./clt-init.sh
cd ..
```

`clt-init.sh` creates:

- `clt_tests/k3s.yaml`: kubeconfig that uses the host-published k3s API, usually `127.0.0.1:6443`.
- `clt_tests/k3s_copy.yaml`: kubeconfig with the Docker bridge IP. This is useful from containers that can reach the Docker bridge directly.
- `manticoresearch/helm-test-kit:0.0.1`: Docker image used by CLT.

From the host, prefer `clt_tests/k3s.yaml`:

```bash
kubectl --kubeconfig clt_tests/k3s.yaml get nodes
```

## Running one test locally

Example for the scaling/SST test:

```bash
CLT_RUN_ARGS='-e TELEMETRY=0 --net=host -v '"$(pwd)"'/clt_tests/k3s.yaml:/tmp/output/kubeconfig-latest.yaml -v '"$(pwd)"'/charts/:/.clt/charts/' \
  ../clt/clt test -d -t clt_tests/tests/sst-scale-replication.rec manticoresearch/helm-test-kit:0.0.1
```

The init block in each test exports:

```bash
KUBECONFIG=/tmp/output/kubeconfig-latest.yaml
```

## CI scenarios

CI runs standalone scenario recordings in parallel. Do not add CI-only dependencies between separate `.rec` files. Put shared setup in `clt_tests/tests/init/*.recb` helpers and include those helpers from each standalone scenario.

Use `clt_tests/tests/init/install.recb` for Helm installs. Each scenario can write `/tmp/clt-values.yaml` before including it to control chart values while keeping the install step shared.

Current standalone scenarios:

- `clt_tests/tests/default-flow.rec`
- `clt_tests/tests/no-balancer-flow.rec`
- `clt_tests/tests/stopwords-flow.rec`
- `clt_tests/tests/sst-scale-replication.rec`
- `clt_tests/tests/wordforms-configmap.rec`

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
