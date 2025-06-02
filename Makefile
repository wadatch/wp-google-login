PLUGIN_NAME = wp-google-login
DIST_DIR = dist
BUILD_DIR = $(PLUGIN_NAME)_build
ZIP_FILE = $(PLUGIN_NAME).zip

.PHONY: build clean

build:
	mkdir -p $(DIST_DIR)
	bash build-plugin.sh

clean:
	rm -rf $(BUILD_DIR) $(DIST_DIR)/$(ZIP_FILE)
	rm -rf vendor composer.lock 