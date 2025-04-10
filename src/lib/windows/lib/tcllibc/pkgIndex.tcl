if {![package vsatisfies [package provide Tcl] 8.6]} {return}
package ifneeded tcllibc 0.3.15 [list ::apply {dir {
    source [file join $dir critcl-rt.tcl]
    set path [file join $dir [::critcl::runtime::MapPlatform]]
    set ext [info sharedlibextension]
    set lib [file join $path "tcllibc$ext"]
    load $lib Tcllibc
    package provide tcllibc 0.3.15
}} $dir]
