# Usage:
# make compile PLUGIN_VERSION=2.0.0

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
