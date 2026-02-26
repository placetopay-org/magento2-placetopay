# Usage:
# make compile PLUGIN_VERSION=2.1.0

.PHONY: compile
compile:
	bash ./generate-white-label.sh "$(PLUGIN_VERSION)"
