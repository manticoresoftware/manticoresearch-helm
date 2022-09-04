## Usage

[Helm](https://helm.sh) must be installed to use the charts.  Please refer to
Helm's [documentation](https://helm.sh/docs) to get started.

Once Helm has been set up correctly, add the repo as follows:

helm repo add manticoresearch https://manticoresoftware.github.io/manticoresearch-helm

If you had already added this repo earlier, run `helm repo update` to retrieve
the latest versions of the packages.  You can then run `helm search repo
manticoresearch` to see the charts.

To install the `manticoresearch-helm` chart:

helm install my-<chart-name> manticoresearch/manticoresearch-helm

    To uninstall the chart:

    helm delete my-<chart-name>

ManticoreSearch Helm repo [documentation](https://github.com/djklim87/manticoresearch-helm#manticore-search-helm-chart) 
