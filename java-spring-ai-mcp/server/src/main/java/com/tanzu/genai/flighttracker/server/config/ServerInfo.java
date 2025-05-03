package com.tanzu.genai.flighttracker.server.config;

/**
 * Class that holds information about the MCP server.
 * Used to provide metadata about the server to clients.
 */
public class ServerInfo {
    private final String name;
    private final String description;
    private final String version;

    /**
     * Creates a new ServerInfo instance.
     *
     * @param name The server name
     * @param description The server description
     * @param version The server version
     */
    public ServerInfo(String name, String description, String version) {
        this.name = name;
        this.description = description;
        this.version = version;
    }

    /**
     * @return The server name
     */
    public String getName() {
        return name;
    }

    /**
     * @return The server description
     */
    public String getDescription() {
        return description;
    }

    /**
     * @return The server version
     */
    public String getVersion() {
        return version;
    }
}
