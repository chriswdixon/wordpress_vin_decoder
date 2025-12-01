document.addEventListener("DOMContentLoaded", function() {
                
                // Function to decode VIN using multiple APIs for comprehensive data
                async function decodeVIN(vin) {
                    try {
                        // Clean and validate VIN
                        const cleanVin = vin.replace(/[^A-HJ-NPR-Z0-9]/gi, "").toUpperCase();
                        
                        if (cleanVin.length !== 17) {
                            throw new Error("Invalid VIN length. VIN must be 17 characters.");
                        }
                        
                        // Option 1: Enhanced NHTSA API call with more fields
                        const nhtsaResponse = await fetch(`https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVin/${cleanVin}?format=json`);
                        
                        if (!nhtsaResponse.ok) {
                            throw new Error(`HTTP error! status: ${nhtsaResponse.status}`);
                        }
                        
                        const nhtsaData = await nhtsaResponse.json();
                        
                        // Extract comprehensive information from NHTSA response
                        const vinInfo = {};
                        
                        if (nhtsaData.Results && Array.isArray(nhtsaData.Results)) {
                            nhtsaData.Results.forEach(item => {
                                if (item.Value && item.Value !== "Not Applicable" && item.Value !== "") {
                                    switch (item.Variable) {
                                        // Basic Info
                                        case "Make":
                                            vinInfo.make = item.Value;
                                            break;
                                        case "Model":
                                            vinInfo.model = item.Value;
                                            break;
                                        case "Model Year":
                                            vinInfo.year = item.Value;
                                            break;
                                        case "Trim":
                                            vinInfo.trim = item.Value;
                                            break;
                                        case "Series":
                                            vinInfo.series = item.Value;
                                            break;
                                        
                                        // Vehicle Type & Body
                                        case "Body Class":
                                            vinInfo.bodyClass = item.Value;
                                            break;
                                        case "Vehicle Type":
                                            vinInfo.vehicleType = item.Value;
                                            break;
                                        case "Vehicle Descriptor":
                                            vinInfo.descriptor = item.Value;
                                            break;
                                        case "Number of Doors":
                                            vinInfo.doors = item.Value;
                                            break;
                                        case "Number of Seats":
                                            vinInfo.seats = item.Value;
                                            break;
                                        case "Number of Seat Rows":
                                            vinInfo.seatRows = item.Value;
                                            break;
                                        
                                        // Engine Details
                                        case "Engine Number of Cylinders":
                                            vinInfo.cylinders = item.Value;
                                            break;
                                        case "Displacement (L)":
                                            vinInfo.displacement = item.Value;
                                            break;
                                        case "Displacement (CI)":
                                            vinInfo.displacementCI = item.Value;
                                            break;
                                        case "Engine Model":
                                            vinInfo.engineModel = item.Value;
                                            break;
                                        case "Engine HP":
                                            vinInfo.horsepower = item.Value;
                                            break;
                                        case "Engine HP (to)":
                                            vinInfo.horsepowerTo = item.Value;
                                            break;
                                        case "Engine Configuration":
                                            vinInfo.engineConfig = item.Value;
                                            break;
                                        
                                        // Fuel & Performance
                                        case "Fuel Type - Primary":
                                            vinInfo.fuelType = item.Value;
                                            break;
                                        case "Fuel Type - Secondary":
                                            vinInfo.fuelTypeSecondary = item.Value;
                                            break;
                                        case "Fuel Delivery / Fuel Injection Type":
                                            vinInfo.fuelInjection = item.Value;
                                            break;
                                        case "Turbo":
                                            vinInfo.turbo = item.Value;
                                            break;
                                        case "Supercharger":
                                            vinInfo.supercharger = item.Value;
                                            break;
                                        
                                        // Drivetrain
                                        case "Transmission Style":
                                            vinInfo.transmission = item.Value;
                                            break;
                                        case "Transmission Speeds":
                                            vinInfo.transmissionSpeeds = item.Value;
                                            break;
                                        case "Drive Type":
                                            vinInfo.driveType = item.Value;
                                            break;
                                        case "Axles":
                                            vinInfo.axles = item.Value;
                                            break;
                                        case "Axle Configuration":
                                            vinInfo.axleConfig = item.Value;
                                            break;
                                        
                                        // Dimensions & Weight
                                        case "Gross Vehicle Weight Rating From":
                                            vinInfo.gvwrFrom = item.Value;
                                            break;
                                        case "Gross Vehicle Weight Rating To":
                                            vinInfo.gvwrTo = item.Value;
                                            break;
                                        case "Curb Weight (pounds)":
                                            vinInfo.curbWeight = item.Value;
                                            break;
                                        case "Wheelbase (inches)":
                                            vinInfo.wheelbase = item.Value;
                                            break;
                                        case "Track Width (inches)":
                                            vinInfo.trackWidth = item.Value;
                                            break;
                                        
                                        // Safety & Features
                                        case "Air Bag Localization":
                                            vinInfo.airbags = item.Value;
                                            break;
                                        case "Anti-lock Braking System (ABS)":
                                            vinInfo.abs = item.Value;
                                            break;
                                        case "Electronic Stability Control (ESC)":
                                            vinInfo.esc = item.Value;
                                            break;
                                        case "Traction Control System (TCS)":
                                            vinInfo.tcs = item.Value;
                                            break;
                                        
                                        // Manufacturing
                                        case "Manufacturer Name":
                                            vinInfo.manufacturer = item.Value;
                                            break;
                                        case "Plant City":
                                            vinInfo.plantCity = item.Value;
                                            break;
                                        case "Plant Country":
                                            vinInfo.plantCountry = item.Value;
                                            break;
                                        case "Plant State":
                                            vinInfo.plantState = item.Value;
                                            break;
                                        case "Plant Company Name":
                                            vinInfo.plantCompany = item.Value;
                                            break;
                                    }
                                }
                            });
                        }
                        
                        // Option 2: Try VinDecoder.eu API for additional data (free tier available)
                        try {
                            const vindecoderResponse = await fetch(`https://api.vindecoder.eu/3.2/${cleanVin}/decode/json`);
                            if (vindecoderResponse.ok) {
                                const vindecoderData = await vindecoderResponse.json();
                                if (vindecoderData.decode && vindecoderData.decode.length > 0) {
                                    const decode = vindecoderData.decode[0];
                                    // Add additional data from VinDecoder if available
                                    if (decode.msrp && !vinInfo.msrp) vinInfo.msrp = decode.msrp;
                                    if (decode.category && !vinInfo.category) vinInfo.category = decode.category;
                                }
                            }
                        } catch (e) {
                            console.log("VinDecoder.eu API not available, using NHTSA only");
                        }
                        
                        return vinInfo;
                        
                    } catch (error) {
                        console.error("Error decoding VIN:", error);
                        throw error;
                    }
                }
                
                // Function to format comprehensive VIN data
                function formatVinData(vinInfo) {
                    const sections = [];
                    
                    // Basic Vehicle Information
                    const basic = [];
                    if (vinInfo.year) basic.push(`Year: ${vinInfo.year}`);
                    if (vinInfo.make) basic.push(`Make: ${vinInfo.make}`);
                    if (vinInfo.model) basic.push(`Model: ${vinInfo.model}`);
                    if (vinInfo.trim) basic.push(`Trim: ${vinInfo.trim}`);
                    if (vinInfo.series) basic.push(`Series: ${vinInfo.series}`);
                    if (basic.length > 0) sections.push("=== BASIC INFORMATION ===\\n" + basic.join("\\n"));
                    
                    // Vehicle Type & Body
                    const body = [];
                    if (vinInfo.bodyClass) body.push(`Body Class: ${vinInfo.bodyClass}`);
                    if (vinInfo.vehicleType) body.push(`Vehicle Type: ${vinInfo.vehicleType}`);
                    if (vinInfo.doors) body.push(`Doors: ${vinInfo.doors}`);
                    if (vinInfo.seats) body.push(`Seats: ${vinInfo.seats}`);
                    if (vinInfo.seatRows) body.push(`Seat Rows: ${vinInfo.seatRows}`);
                    if (body.length > 0) sections.push("=== BODY & CONFIGURATION ===\\n" + body.join("\\n"));
                    
                    // Engine Details
                    const engine = [];
                    if (vinInfo.cylinders) engine.push(`Cylinders: ${vinInfo.cylinders}`);
                    if (vinInfo.displacement) engine.push(`Engine Size: ${vinInfo.displacement}L`);
                    if (vinInfo.displacementCI) engine.push(`Engine Size: ${vinInfo.displacementCI} CI`);
                    if (vinInfo.engineModel) engine.push(`Engine Model: ${vinInfo.engineModel}`);
                    if (vinInfo.horsepower) engine.push(`Horsepower: ${vinInfo.horsepower}${vinInfo.horsepowerTo ? ` - ${vinInfo.horsepowerTo}` : ""}`);
                    if (vinInfo.engineConfig) engine.push(`Engine Config: ${vinInfo.engineConfig}`);
                    if (vinInfo.turbo && vinInfo.turbo !== "No") engine.push(`Turbo: ${vinInfo.turbo}`);
                    if (vinInfo.supercharger && vinInfo.supercharger !== "No") engine.push(`Supercharger: ${vinInfo.supercharger}`);
                    if (engine.length > 0) sections.push("=== ENGINE SPECIFICATIONS ===\\n" + engine.join("\\n"));
                    
                    // Fuel System
                    const fuel = [];
                    if (vinInfo.fuelType) fuel.push(`Primary Fuel: ${vinInfo.fuelType}`);
                    if (vinInfo.fuelTypeSecondary) fuel.push(`Secondary Fuel: ${vinInfo.fuelTypeSecondary}`);
                    if (vinInfo.fuelInjection) fuel.push(`Fuel Injection: ${vinInfo.fuelInjection}`);
                    if (fuel.length > 0) sections.push("=== FUEL SYSTEM ===\\n" + fuel.join("\\n"));
                    
                    // Drivetrain
                    const drivetrain = [];
                    if (vinInfo.transmission) drivetrain.push(`Transmission: ${vinInfo.transmission}`);
                    if (vinInfo.transmissionSpeeds) drivetrain.push(`Transmission Speeds: ${vinInfo.transmissionSpeeds}`);
                    if (vinInfo.driveType) drivetrain.push(`Drive Type: ${vinInfo.driveType}`);
                    if (vinInfo.axles) drivetrain.push(`Axles: ${vinInfo.axles}`);
                    if (vinInfo.axleConfig) drivetrain.push(`Axle Configuration: ${vinInfo.axleConfig}`);
                    if (drivetrain.length > 0) sections.push("=== DRIVETRAIN ===\\n" + drivetrain.join("\\n"));
                    
                    // Dimensions & Weight
                    const dimensions = [];
                    if (vinInfo.gvwrFrom) dimensions.push(`GVWR: ${vinInfo.gvwrFrom}${vinInfo.gvwrTo ? ` - ${vinInfo.gvwrTo}` : ""} lbs`);
                    if (vinInfo.curbWeight) dimensions.push(`Curb Weight: ${vinInfo.curbWeight} lbs`);
                    if (vinInfo.wheelbase) dimensions.push(`Wheelbase: ${vinInfo.wheelbase} inches`);
                    if (vinInfo.trackWidth) dimensions.push(`Track Width: ${vinInfo.trackWidth} inches`);
                    if (dimensions.length > 0) sections.push("=== DIMENSIONS & WEIGHT ===\\n" + dimensions.join("\\n"));
                    
                    // Safety Features
                    const safety = [];
                    if (vinInfo.airbags && vinInfo.airbags !== "Not Applicable") safety.push(`Airbags: ${vinInfo.airbags}`);
                    if (vinInfo.abs && vinInfo.abs !== "No") safety.push(`ABS: ${vinInfo.abs}`);
                    if (vinInfo.esc && vinInfo.esc !== "No") safety.push(`Electronic Stability Control: ${vinInfo.esc}`);
                    if (vinInfo.tcs && vinInfo.tcs !== "No") safety.push(`Traction Control: ${vinInfo.tcs}`);
                    if (safety.length > 0) sections.push("=== SAFETY FEATURES ===\\n" + safety.join("\\n"));
                    
                    // Manufacturing Info
                    const manufacturing = [];
                    if (vinInfo.manufacturer) manufacturing.push(`Manufacturer: ${vinInfo.manufacturer}`);
                    if (vinInfo.plantCity || vinInfo.plantState || vinInfo.plantCountry) {
                        const location = [vinInfo.plantCity, vinInfo.plantState, vinInfo.plantCountry].filter(Boolean).join(", ");
                        manufacturing.push(`Plant Location: ${location}`);
                    }
                    if (vinInfo.plantCompany) manufacturing.push(`Plant Company: ${vinInfo.plantCompany}`);
                    if (manufacturing.length > 0) sections.push("=== MANUFACTURING ===\\n" + manufacturing.join("\\n"));
                    
                    // Additional Data (if available from secondary APIs)
                    const additional = [];
                    if (vinInfo.msrp) additional.push(`MSRP: ${vinInfo.msrp}`);
                    if (vinInfo.category) additional.push(`Category: ${vinInfo.category}`);
                    if (additional.length > 0) sections.push("=== ADDITIONAL INFO ===\\n" + additional.join("\\n"));
                    
                    return sections.join("\\n\\n");
                }
                
                // Function to find VIN field in Contact Form 7
                function findVinField(form) {
                    // CF7 uses name attributes like "vin", "vehicle-vin", etc.
                    // Search for both lowercase and uppercase variations
                    let vinField = form.find("input").filter(function() {
                        const name = $(this).attr("name");
                        return name && (name.toLowerCase().indexOf("vin") !== -1);
                    }).first();
                    
                    // Try by class name (CF7 often uses classes)
                    if (!vinField.length) {
                        vinField = form.find("input").filter(function() {
                            const className = $(this).attr("class");
                            return className && (className.toLowerCase().indexOf("vin") !== -1);
                        }).first();
                    }
                    
                    // Try by ID
                    if (!vinField.length) {
                        vinField = form.find("input").filter(function() {
                            const id = $(this).attr("id");
                            return id && (id.toLowerCase().indexOf("vin") !== -1);
                        }).first();
                    }
                    
                    // Try by placeholder
                    if (!vinField.length) {
                        vinField = form.find("input").filter(function() {
                            const placeholder = $(this).attr("placeholder");
                            return placeholder && (placeholder.toLowerCase().indexOf("vin") !== -1);
                        }).first();
                    }
                    
                    // You can specify exact field name here if needed:
                    // vinField = form.find("input[name=\"vin\"]"); // Adjust field name as needed
                    
                    return vinField;
                }
                
                // Method 1: Hook into Contact Form 7 submit using wpcf7submit event
                $(document).on("wpcf7submit", async function(event) {
                    console.log("CF7 submit event triggered");
                    
                    const form = $(event.target);
                    const vinField = findVinField(form);
                    
                    console.log("Found VIN field:", vinField.length);
                    
                    if (vinField.length && vinField.val().trim()) {
                        console.log("VIN value:", vinField.val());
                        
                        try {
                            const vinInfo = await decodeVIN(vinField.val().trim());
                            const formattedData = formatVinData(vinInfo);
                            
                            console.log("VIN decoded:", formattedData);
                            
                            // Store VIN data for email processing
                            window.cf7_vin_data = formattedData;
                            
                        } catch (error) {
                            console.error("VIN processing error:", error);
                            // Continue with form submission even if VIN fails
                        }
                    }
                });
                
                // Method 2: Intercept form submission before it happens
                $(document).on("submit", ".wpcf7-form", async function(e) {
                    console.log("CF7 form submit intercepted");
                    
                    const form = $(this);
                    const vinField = findVinField(form);
                    
                    console.log("Found VIN field:", vinField.length);
                    
                    if (vinField.length && vinField.val().trim()) {
                        const vinValue = vinField.val().trim();
                        console.log("Processing VIN:", vinValue);
                        
                        // Check if we already processed this VIN
                        if (form.data("vin-processed")) {
                            console.log("VIN already processed, allowing submission");
                            return true;
                        }
                        
                        e.preventDefault(); // Prevent submission until VIN is processed
                        
                        const submitButton = form.find("input[type=\"submit\"], button[type=\"submit\"]");
                        
                        // Show loading state
                        submitButton.prop("disabled", true);
                        const originalValue = submitButton.val() || submitButton.text();
                        
                        if (submitButton.is("input")) {
                            submitButton.val("Processing VIN...");
                        } else {
                            submitButton.text("Processing VIN...");
                        }
                        
                        try {
                            const vinInfo = await decodeVIN(vinValue);
                            const formattedData = formatVinData(vinInfo);
                            
                            console.log("VIN decoded successfully:", formattedData);
                            
                            // Add VIN data to form
                            let vinDataField = form.find("input[name=\"vin_data\"], textarea[name=\"vin_data\"]");
                            
                            if (!vinDataField.length) {
                                // Create hidden field for VIN data
                                vinDataField = $("<input type=\"hidden\" name=\"vin_data\" />");
                                form.append(vinDataField);
                                console.log("Created hidden VIN data field");
                            }
                            
                            vinDataField.val(formattedData);
                            
                            // Alternative: Append to message/comments field
                            const messageField = form.find("textarea").filter(function() {
                                const name = $(this).attr("name");
                                return name && (
                                    name.toLowerCase().indexOf("message") !== -1 ||
                                    name.toLowerCase().indexOf("comment") !== -1 ||
                                    name.toLowerCase().indexOf("note") !== -1
                                );
                            }).first();
                            if (messageField.length) {
                                const currentValue = messageField.val();
                                const separator = currentValue ? "\\n\\n--- Vehicle Information ---\\n" : "--- Vehicle Information ---\\n";
                                messageField.val(currentValue + separator + formattedData);
                                console.log("Added VIN data to message field");
                            }
                            
                            // Mark as processed and resubmit
                            form.data("vin-processed", true);
                            form.submit();
                            
                        } catch (error) {
                            alert("Error processing VIN: " + error.message + ". Please verify the VIN and try again.");
                            console.error("VIN processing failed:", error);
                        } finally {
                            // Restore button state
                            submitButton.prop("disabled", false);
                            if (submitButton.is("input")) {
                                submitButton.val(originalValue);
                            } else {
                                submitButton.text(originalValue);
                            }
                        }
                    }
                });
                
                // Debug: Show form structure when page loads
                setTimeout(function() {
                    console.log("=== CF7 VIN Integration Debug ===");
                    const forms = $(".wpcf7-form");
                    console.log("Found CF7 forms:", forms.length);
                    
                    forms.each(function(i) {
                        const form = $(this);
                        console.log(`Form ${i}:`, form);
                        
                        const vinField = findVinField(form);
                        console.log(`VIN field found in form ${i}:`, vinField.length ? "YES" : "NO");
                        
                        if (vinField.length) {
                            console.log("VIN field details:", {
                                name: vinField.attr("name"),
                                id: vinField.attr("id"),
                                class: vinField.attr("class"),
                                placeholder: vinField.attr("placeholder")
                            });
                        }
                        
                        // Show all input fields for debugging
                        console.log("All input fields in form " + i + ":");
                        form.find("input, textarea").each(function(j) {
                            console.log(`  Field ${j}:`, {
                                tag: this.tagName,
                                name: $(this).attr("name"),
                                id: $(this).attr("id"),
                                class: $(this).attr("class"),
                                type: $(this).attr("type"),
                                placeholder: $(this).attr("placeholder")
                            });
                        });
                    });
                }, 1000);
                
            });
