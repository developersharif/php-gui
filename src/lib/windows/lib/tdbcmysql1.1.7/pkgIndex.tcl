# Index file to load the TDBC MySQL package.

if {![package vsatisfies [package provide Tcl] 8.6-]} {
    return
}
if {[package vsatisfies [package provide Tcl] 9.0-]} {
    package ifneeded tdbc::mysql 1.1.7 \
	    "[list source [file join $dir tdbcmysql.tcl]]\;\
	    [list load [file join $dir tcl9tdbcmysql117t.dll] [string totitle tdbcmysql]]"
} else {
    package ifneeded tdbc::mysql 1.1.7 \
	    "[list source [file join $dir tdbcmysql.tcl]]\;\
	    [list load [file join $dir tdbcmysql117t.dll] [string totitle tdbcmysql]]"
}
