package ifneeded nx 2.4.0 "[list source [file join $dir nx.tcl]]; [list package provide nx 2.4.0]"
package ifneeded nx::class-method 1.0 "[list source [file join $dir class-method.tcl]]; [list package provide nx::class-method 1.0]"
package ifneeded nx::plain-object-method 1.0 "[list source [file join $dir plain-object-method.tcl]]; [list package provide nx::plain-object-method 1.0]"
