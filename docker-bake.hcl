variable "TAGS" {
    type = list(string)
    default = ["latest"]
}

variable "IMAGES_PREFIX" {
    type = string
}

target "docker-metadata-action" {}

target "api" {
    inherits = ["docker-metadata-action"]
    tags = [for tag in TAGS: "${IMAGES_PREFIX}-api:${tag}"]
}

target "pwa" {
    inherits = ["docker-metadata-action"]
    tags = [for tag in TAGS: "${IMAGES_PREFIX}-pwa:${tag}"]
}

# Only the published images; the worker uses the api image
group "default" {
    targets = ["api", "pwa"]
}
