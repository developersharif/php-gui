cmake_minimum_required(VERSION 3.16)
project(webview_helper C CXX)

set(CMAKE_C_STANDARD 11)
set(CMAKE_CXX_STANDARD 14)

# Fetch webview/webview library
include(FetchContent)
FetchContent_Declare(
    webview
    GIT_REPOSITORY https://github.com/webview/webview.git
    GIT_TAG        0.12.0
)
FetchContent_MakeAvailable(webview)

# Build the helper binary
# webview is header-only C++ — main source must be .cc
# cJSON is pure C, compiled separately
add_executable(webview_helper webview_helper.cc cJSON.c)
target_link_libraries(webview_helper PRIVATE webview::core)

# Platform-specific threading
if(UNIX AND NOT APPLE)
    target_link_libraries(webview_helper PRIVATE pthread)
endif()

# Determine platform and arch for output naming
string(TOLOWER "${CMAKE_SYSTEM_NAME}" PLATFORM_NAME)
if(PLATFORM_NAME STREQUAL "darwin")
    set(PLATFORM_NAME "darwin")
elseif(PLATFORM_NAME STREQUAL "windows")
    set(PLATFORM_NAME "windows")
else()
    set(PLATFORM_NAME "linux")
endif()

# Normalize architecture name
set(ARCH_NAME "${CMAKE_SYSTEM_PROCESSOR}")
if(ARCH_NAME STREQUAL "AMD64" OR ARCH_NAME STREQUAL "x86_64")
    set(ARCH_NAME "x86_64")
elseif(ARCH_NAME STREQUAL "arm64" OR ARCH_NAME STREQUAL "aarch64")
    set(ARCH_NAME "aarch64")
    if(APPLE)
        set(ARCH_NAME "arm64")
    endif()
endif()

set_target_properties(webview_helper PROPERTIES
    OUTPUT_NAME "webview_helper_${PLATFORM_NAME}_${ARCH_NAME}"
    RUNTIME_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/.."
)

message(STATUS "Building: webview_helper_${PLATFORM_NAME}_${ARCH_NAME}")
