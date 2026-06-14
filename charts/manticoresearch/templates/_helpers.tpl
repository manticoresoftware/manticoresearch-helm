{{/*
Expand the name of the chart.
*/}}
{{- define "manticoresearch.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
If release name contains chart name it will be used as a full name.
*/}}
{{- define "manticoresearch.fullname" -}}
{{- if .Values.fullNameOverride }}
{{- .Values.fullNameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "manticoresearch.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "manticoresearch.labels" -}}
helm.sh/chart: {{ include "manticoresearch.chart" . }}
{{ include "manticoresearch.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "manticoresearch.selectorLabels" -}}
app.kubernetes.io/name: {{ include "manticoresearch.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
User-provided pod labels.
These labels are intentionally rendered only on Pod templates, not workload
selectors, so operators can change traffic-routing labels without hitting
immutable selector fields or changing chart ownership labels.
*/}}
{{- define "manticoresearch.workerPodLabels" -}}
{{- $labels := dict -}}
{{- with .Values.podLabels -}}
{{- if not (kindIs "map" .) -}}
{{- fail "podLabels must be a map of label keys to label values" -}}
{{- end -}}
{{- $labels = mergeOverwrite $labels . -}}
{{- end -}}
{{- with .Values.worker.podLabels -}}
{{- if not (kindIs "map" .) -}}
{{- fail "worker.podLabels must be a map of label keys to label values" -}}
{{- end -}}
{{- $labels = mergeOverwrite $labels . -}}
{{- end -}}
{{- $labels = omit $labels "name" "app.kubernetes.io/name" "app.kubernetes.io/instance" "app.kubernetes.io/component" -}}
{{- range $key, $value := $labels }}
{{- if or (kindIs "map" $value) (kindIs "slice" $value) -}}
{{- fail (printf "pod label %q must have a scalar value" $key) -}}
{{- end -}}
{{- printf "%s: %s\n" $key ($value | toString | quote) -}}
{{- end -}}
{{- end -}}

{{- define "manticoresearch.balancerPodLabels" -}}
{{- $labels := dict -}}
{{- with .Values.podLabels -}}
{{- if not (kindIs "map" .) -}}
{{- fail "podLabels must be a map of label keys to label values" -}}
{{- end -}}
{{- $labels = mergeOverwrite $labels . -}}
{{- end -}}
{{- with .Values.balancer.podLabels -}}
{{- if not (kindIs "map" .) -}}
{{- fail "balancer.podLabels must be a map of label keys to label values" -}}
{{- end -}}
{{- $labels = mergeOverwrite $labels . -}}
{{- end -}}
{{- $labels = omit $labels "name" "app.kubernetes.io/name" "app.kubernetes.io/instance" "app.kubernetes.io/component" -}}
{{- range $key, $value := $labels }}
{{- if or (kindIs "map" $value) (kindIs "slice" $value) -}}
{{- fail (printf "pod label %q must have a scalar value" $key) -}}
{{- end -}}
{{- printf "%s: %s\n" $key ($value | toString | quote) -}}
{{- end -}}
{{- end -}}

{{/*
Create the name of the service account to use
*/}}
{{- define "manticoresearch.serviceAccountName" -}}
{{- default (include "manticoresearch.fullname" .) .Values.serviceAccount.name }}
{{- end }}

{{/*
Return  the proper Storage Class for worker
*/}}
{{- define "manticoresearch.worker.storageClass" -}}
{{/*
Helm 2.11 supports the assignment of a value to a variable defined in a different scope,
but Helm 2.9 and 2.10 does not support it, so we need to implement this if-else logic.
*/}}
{{- if .Values.global -}}
    {{- if .Values.global.storageClass -}}
        {{- if (eq "-" .Values.global.storageClass) -}}
            {{- printf "storageClassName: \"\"" -}}
        {{- else }}
            {{- printf "storageClassName: %s" .Values.global.storageClass -}}
        {{- end -}}
    {{- else -}}
        {{- if .Values.worker.persistence.storageClass -}}
              {{- if (eq "-" .Values.worker.persistence.storageClass) -}}
                  {{- printf "storageClassName: \"\"" -}}
              {{- else }}
                  {{- printf "storageClassName: %s" .Values.worker.persistence.storageClass -}}
              {{- end -}}
        {{- end -}}
    {{- end -}}
{{- else -}}
    {{- if .Values.worker.persistence.storageClass -}}
        {{- if (eq "-" .Values.worker.persistence.storageClass) -}}
            {{- printf "storageClassName: \"\"" -}}
        {{- else }}
            {{- printf "storageClassName: %s" .Values.worker.persistence.storageClass -}}
        {{- end -}}
    {{- end -}}
{{- end -}}
{{- end -}}


{{/*
Substitute key for values to template
*/}}
{{- define "manticore-helm.render" -}}
{{- $value := .value | toYaml | trim }}
{{ $value | indent 2 }}
{{- end -}}