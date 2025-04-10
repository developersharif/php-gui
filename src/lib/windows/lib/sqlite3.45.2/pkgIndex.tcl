# -*- tcl -*-
# Tcl package index file, version 1.1
#
if {[package vsatisfies [package provide Tcl] 9.0-]} {
    package ifneeded sqlite3 3.45.2 \
	    [list load [file join $dir tcl9sqlite3452.dll] sqlite3]
} else {
    package ifneeded sqlite3 3.45.2 \
	    [list load [file join $dir sqlite3452.dll] sqlite3]
}
