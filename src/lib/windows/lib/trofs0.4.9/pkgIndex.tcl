if {[package vsatisfies 8.6 8.5]} {
if {![package vsatisfies [package provide Tcl]     8.6]} {return}
} elseif {[package vcompare [package provide Tcl]     8.6]} {return}
package ifneeded trofs 0.4.9     [list load [file join $dir trofs049.dll]]
