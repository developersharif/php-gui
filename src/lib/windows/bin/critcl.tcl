#!/bin/sh
# -*-tcl -*-
# hide next line from tcl \
exec "C:/Users/build/AppData/Local/Temp/3/ActiveState-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/bin/tclsh.exe" "$0" ${1+"$@"}

# Add location of critcl packages to the package load path, if not
# yet present. Computed relative to the location of the application,
# as per the installation paths.
set libpath [file join [file dirname [info script]] .. lib]
set libpath [file dirname [file normalize [file join $libpath ...]]]
if {[lsearch -exact $auto_path $libpath] < 0} {
    set auto_path [linsert $auto_path[set auto_path {}] 0 $libpath]
}
unset libpath

package require critcl::app
critcl::app::main $argv
