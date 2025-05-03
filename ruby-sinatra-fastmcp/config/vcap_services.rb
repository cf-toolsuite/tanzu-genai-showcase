require 'json'

module VcapServices
  # Environment Variable Fallbacks:
  # The service will check for credentials in this order:
  # 1. Cloud Foundry VCAP_SERVICES (service binding)
  # 2. Environment variable:
  #    - API Key:AVIATIONSTACK_API_KEY

  # Check if running in Cloud Foundry
  def self.cloud_foundry?
    !ENV['VCAP_APPLICATION'].nil?
  end

  # Helper method to check credentials for API key
  def self.check_credentials_for_api_key(credentials, env_var_name)
    return credentials[env_var_name] if credentials[env_var_name]
    return credentials['api_key'] if credentials['api_key']
    return credentials['apiKey'] if credentials['apiKey']
    nil
  end

  # Get API key from environment variable, service bindings, or fallbacks
  def self.get_api_key(env_var_name)
    # Log the start of the API key retrieval process
    puts "Attempting to retrieve API key for: #{env_var_name}"

    # 1. Check direct environment variable first (works for cf set-env, .env, exported vars)
    if ENV[env_var_name]
      puts "Found #{env_var_name} in environment variables"
      return ENV[env_var_name]
    end

    # 2. If in Cloud Foundry, check for service bindings
    if cloud_foundry? && ENV['VCAP_SERVICES']
      puts "Running in Cloud Foundry, checking VCAP_SERVICES"

      # Parse VCAP_SERVICES
      begin
        vcap_services = JSON.parse(ENV['VCAP_SERVICES'])
        puts "Successfully parsed VCAP_SERVICES"

        # Check all services for a matching credential
        vcap_services.each do |service_type, instances|
          puts "Checking service type: #{service_type}"

          instances.each do |instance|
            puts "Checking instance: #{instance['name'] || 'unnamed'}"

            credentials = instance['credentials']
            next unless credentials

            # Check for API key in credentials
            api_key = check_credentials_for_api_key(credentials, env_var_name)
            if api_key
              puts "Found API key in service credentials"
              return api_key
            end
          end
        end

        puts "No API key found in any service credentials"
      rescue JSON::ParserError => e
        puts "Error parsing VCAP_SERVICES: #{e.message}"
      end
    else
      puts "Not running in Cloud Foundry or VCAP_SERVICES not available"
    end

    # Log that no API key was found
    puts "No API key found for: #{env_var_name}"
    nil
  end
end
