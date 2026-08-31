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
Validate Manticore mlock configuration. access_* = mlock requires the worker
container to be allowed to lock memory, otherwise searchd logs mlock() failed:
Cannot allocate memory regardless of the pod memory limit.
*/}}
{{- define "manticoresearch.validateMlock" -}}
{{- $mlock := default dict .Values.worker.mlock -}}
{{- $usesMlock := regexMatch "(?im)^\\s*access_(plain_attrs|blob_attrs|doclists|hitlists|dict)\\s*=\\s*mlock\\b" (default "" .Values.worker.config.content) -}}
{{- $validate := true -}}
{{- if hasKey $mlock "validate" -}}
  {{- $validate = $mlock.validate -}}
{{- end -}}
{{- if and $usesMlock $validate -}}
  {{- $securityContext := default dict .Values.securityContext -}}
  {{- $capabilities := default dict $securityContext.capabilities -}}
  {{- $add := list -}}
  {{- with $capabilities.add -}}
    {{- $add = . -}}
  {{- end -}}
  {{- $hasIpcLock := or (has "IPC_LOCK" $add) (has "ALL" $add) (eq true $securityContext.privileged) -}}
  {{- if and (not $mlock.enabled) (not $hasIpcLock) -}}
    {{- fail "worker.config.content uses access_* = mlock, but the worker container does not add CAP_IPC_LOCK. Set worker.mlock.enabled=true or add IPC_LOCK to securityContext.capabilities.add; otherwise mlock() can fail with 'Cannot allocate memory' even when the pod has enough RAM." -}}
  {{- end -}}
{{- end -}}
{{- end -}}

{{/*
Render the worker container security context. worker.mlock.enabled adds only
IPC_LOCK and preserves any user-provided securityContext fields.
*/}}
{{- define "manticoresearch.workerSecurityContext" -}}
{{- $mlock := default dict .Values.worker.mlock -}}
{{- $securityContext := deepCopy (default dict .Values.securityContext) -}}
{{- if $mlock.enabled -}}
  {{- $capabilities := deepCopy (default dict $securityContext.capabilities) -}}
  {{- $add := list -}}
  {{- with $capabilities.add -}}
    {{- $add = . -}}
  {{- end -}}
  {{- if not (has "IPC_LOCK" $add) -}}
    {{- $add = append $add "IPC_LOCK" -}}
  {{- end -}}
  {{- $_ := set $capabilities "add" $add -}}
  {{- $_ := set $securityContext "capabilities" $capabilities -}}
{{- end -}}
{{- toYaml $securityContext -}}
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