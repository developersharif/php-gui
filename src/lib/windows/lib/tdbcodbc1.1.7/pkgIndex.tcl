# Index file to load the TDBC ODBC package.

if {![package vsatisfies [package provide Tcl] 8.6-]} {
    return
}
if {[package vsatisfies [package provide Tcl] 9.0-]} {
    package ifneeded tdbc::odbc 1.1.7 \
	    "[list source [file join $dir tdbcodbc.tcl]]\;\
	    [list load [file join $dir tcl9tdbcodbc117t.dll] [string totitle tdbcodbc]]"
} else {
    package ifneeded tdbc::odbc 1.1.7 \
	    "[list source [file join $dir tdbcodbc.tcl]]\;\
	    [list load [file join $dir tdbcodbc117t.dll] [string totitle tdbcodbc]]"
}
