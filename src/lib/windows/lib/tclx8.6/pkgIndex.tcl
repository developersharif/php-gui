# Package index for TclX @TCLX_FULL_VERSION@.
#
if {![package vsatisfies [package provide Tcl] 8.4]} { return }
package ifneeded Tclx 8.6 \
    [list load [file join $dir tclx86.dll] Tclx]
