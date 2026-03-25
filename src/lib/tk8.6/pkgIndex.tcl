if {![package vsatisfies [package provide Tcl] 8.6.0]} return
package ifneeded Tk 8.6.17 [list load [file normalize [file join [file dirname [info script]] .. libtk8.6.so]]]
