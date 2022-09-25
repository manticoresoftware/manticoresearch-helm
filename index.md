## Usage

[Helm](https://helm.sh) must be installed to use the charts.  Please refer to Helm's [documentation](https://helm.sh/docs) to get started.

Once Helm has been set up correctly, add the repo as follows:

```bash
helm repo add manticoresearch https://helm.manticoresearch.com
```

If you had already added this repo earlier, run `helm repo update` to retrieve the latest versions of the packages.  You can then run `helm search repo manticoresearch` to see the charts.

To install the `manticoresearch` chart:
```bash
helm install my-<chart-name> manticoresearch/manticoresearch
```

To uninstall the chart:
```bash
helm delete my-<chart-name>
```

Manticore Search Helm chart [documentation](https://github.com/manticoresoftware/manticoresearch-helm#manticore-search-helm-chart).
