# movie_chatbot/settings/cf_service_config.py

import os
import json
import logging
import cfenv

logger = logging.getLogger(__name__)

# --- Cloud Foundry Environment Setup ---
cf_env = cfenv.AppEnv()
logger.info("Initialized cfenv.AppEnv() for service configuration.")

def get_user_provided_service(name):
    """Get a user-provided service by name"""
    try:
        service = cf_env.get_service(name=name)
        if service:
            logger.info(f"Found user-provided service: {name}")
            return service
    except Exception as e:
        logger.debug(f"Could not find user-provided service {name}: {e}")
    return None

def get_service_credential(service_name, credential_name, default=None):
    """
    Get a credential from a service binding

    Args:
        service_name: Name of the service to look for
        credential_name: Name of the credential to extract
        default: Default value if credential is not found

    Returns:
        The credential value or default if not found
    """
    service = get_user_provided_service(service_name)
    if service and hasattr(service, 'credentials'):
        credentials = service.credentials
        if isinstance(credentials, dict):
            if credential_name in credentials:
                logger.debug(f"Found credential {credential_name} in service {service_name}")
                return credentials.get(credential_name)
            elif 'credhub-ref' in credentials:
                logger.info(f"Service {service_name} uses credhub-ref, but direct access not implemented")
                # In a real implementation, we would access credhub here
                # This would require additional libraries and permissions
    return default

def get_all_service_credentials(service_name):
    """
    Get all credentials from a service binding

    Args:
        service_name: Name of the service to look for

    Returns:
        Dictionary of all credentials or empty dict if not found
    """
    service = get_user_provided_service(service_name)
    if service and hasattr(service, 'credentials'):
        credentials = service.credentials
        if isinstance(credentials, dict):
            if 'credhub-ref' in credentials:
                logger.info(f"Service {service_name} uses credhub-ref, but direct access not implemented")
                # In a real implementation, we would access credhub here
                return {}
            return credentials
    return {}

def is_running_on_cloud_foundry():
    """Check if the application is running on Cloud Foundry"""
    return cf_env.app is not None

def diagnose_cf_environment():
    """Print diagnostic information about CF environment and services"""
    logger.info("--- CF Environment Diagnostics ---")
    # Basic check using cfenv attributes
    is_cf = is_running_on_cloud_foundry()
    logger.info(f"Running in Cloud Foundry (detected by cfenv): {is_cf}")

    if is_cf:
        try:
            logger.info(f"App Name: {cf_env.name}, Instance Index: {cf_env.index}")
            # Access space/org info if available (might require VCAP_APPLICATION)
            vcap_app = cf_env.app if hasattr(cf_env, 'app') else {}
            logger.info(f"Space: {vcap_app.get('space_name', 'N/A')}, Org: {vcap_app.get('organization_name', 'N/A')}")

            all_services = cf_env.services
            if all_services:
                logger.info(f"Bound Services ({len(all_services)}):")
                for service in all_services:
                    # Use cfenv's service object attributes with safe access
                    service_info = []
                    if hasattr(service, 'name'):
                        service_info.append(f"Name: {service.name}")
                    if hasattr(service, 'label'):
                        service_info.append(f"Label: {service.label}")
                    elif hasattr(service, 'tags') and service.tags:
                        service_info.append(f"Tags: {', '.join(service.tags)}")
                    if hasattr(service, 'plan'):
                        service_info.append(f"Plan: {service.plan}")

                    logger.info(f"  - {', '.join(service_info)}")

                    if hasattr(service, 'credentials') and service.credentials:
                        if isinstance(service.credentials, dict):
                            logger.info(f"    Credential Keys: {list(service.credentials.keys())}")
                            if 'credhub-ref' in service.credentials:
                                logger.info(f"    Contains credhub-ref: {service.credentials['credhub-ref']}")
                        else:
                            logger.info(f"    Credentials type: {type(service.credentials)}")
                    else:
                        logger.info("    Credentials not available or empty.")
            else:
                logger.info("No bound services found via cfenv.")
        except Exception as e:
            logger.error(f"Error during CF diagnostics: {e}", exc_info=True)
    else:
        logger.info("Not running in Cloud Foundry (or cfenv couldn't detect it).")
    logger.info("--- End CF Environment Diagnostics ---")
