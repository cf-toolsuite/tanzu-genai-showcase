package logger

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"os"
	"strings"
	"time"
)

var (
	InfoLogger     *log.Logger
	ErrorLogger    *log.Logger
	DebugLogger    *log.Logger
	LLMLogger      *log.Logger
	APILogger      *log.Logger
	ToolLogger     *log.Logger
	DecisionLogger *log.Logger
)

// ToolCallRecord represents a record of a tool call for tracking sequences
type ToolCallRecord struct {
	ID        string    `json:"id"`
	Name      string    `json:"name"`
	Args      string    `json:"args"`
	Timestamp time.Time `json:"timestamp"`
	Response  string    `json:"response,omitempty"`
	Error     string    `json:"error,omitempty"`
}

// Global tool call sequence for tracking
var toolCallSequence []ToolCallRecord

// Init initializes all loggers
func Init() error {
	// Create logs directory if it doesn't exist
	if _, err := os.Stat("logs"); os.IsNotExist(err) {
		if err := os.Mkdir("logs", 0755); err != nil {
			return fmt.Errorf("failed to create logs directory: %w", err)
		}
	}

	// Create log files
	infoFile, err := os.OpenFile("logs/info.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return fmt.Errorf("failed to open info log file: %w", err)
	}

	errorFile, err := os.OpenFile("logs/error.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return fmt.Errorf("failed to open error log file: %w", err)
	}

	llmFile, err := os.OpenFile("logs/llm.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return fmt.Errorf("failed to open llm log file: %w", err)
	}

	apiFile, err := os.OpenFile("logs/api.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return fmt.Errorf("failed to open api log file: %w", err)
	}

	toolFile, err := os.OpenFile("logs/tool.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return fmt.Errorf("failed to open tool log file: %w", err)
	}

	decisionFile, err := os.OpenFile("logs/decision.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return fmt.Errorf("failed to open decision log file: %w", err)
	}

	// Set up loggers with file and line information
	InfoLogger = log.New(io.MultiWriter(os.Stdout, infoFile), "INFO: ", log.Ldate|log.Ltime)
	ErrorLogger = log.New(io.MultiWriter(os.Stderr, errorFile), "ERROR: ", log.Ldate|log.Ltime|log.Lshortfile)
	DebugLogger = log.New(io.MultiWriter(os.Stdout, infoFile), "DEBUG: ", log.Ldate|log.Ltime|log.Lshortfile)
	LLMLogger = log.New(io.MultiWriter(os.Stdout, llmFile), "LLM: ", log.Ldate|log.Ltime)
	APILogger = log.New(io.MultiWriter(os.Stdout, apiFile), "API: ", log.Ldate|log.Ltime)
	ToolLogger = log.New(io.MultiWriter(os.Stdout, toolFile), "TOOL: ", log.Ldate|log.Ltime)
	DecisionLogger = log.New(io.MultiWriter(os.Stdout, decisionFile), "DECISION: ", log.Ldate|log.Ltime)

	// Initialize tool call sequence
	toolCallSequence = make([]ToolCallRecord, 0)

	return nil
}

// LogLLMRequest logs LLM request details
func LogLLMRequest(prompt string, tools []string) {
	LLMLogger.Printf("REQUEST:\nPrompt: %s\nTools Available: %s",
		truncateForLog(prompt, 1000), strings.Join(tools, ", "))
}

// LogLLMResponse logs LLM response details
func LogLLMResponse(response string) {
	LLMLogger.Printf("RESPONSE:\n%s", truncateForLog(response, 1000))
}

// LogToolCall logs tool call details
func LogToolCall(toolName string, args string) {
	LLMLogger.Printf("TOOL CALL:\nTool: %s\nArguments: %s", toolName, args)
}

// LogAPIRequest logs API request details
func LogAPIRequest(endpoint string, params map[string]string) {
	APILogger.Printf("REQUEST:\nEndpoint: %s\nParams: %+v", endpoint, params)
}

// LogAPIResponse logs API response details
func LogAPIResponse(endpoint string, response string) {
	APILogger.Printf("RESPONSE from %s:\n%s", endpoint, truncateForLog(response, 1000))
}

// LogFallback logs fallback to direct response
func LogFallback(reason string) {
	LLMLogger.Printf("FALLBACK: %s", reason)
}

// Helper to truncate long strings for logging
func truncateForLog(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen] + "... [truncated]"
}

// LogToolCallResult logs the result of a tool call
func LogToolCallResult(toolName string, result string, err error) {
	if err != nil {
		LLMLogger.Printf("TOOL RESULT:\nTool: %s\nError: %v", toolName, err)
	} else {
		LLMLogger.Printf("TOOL RESULT:\nTool: %s\nResult: %s", toolName, truncateForLog(result, 1000))
	}
}

// LogConversationState logs the current state of the conversation
func LogConversationState(messageCount int, hasToolCalls bool) {
	LLMLogger.Printf("CONVERSATION STATE:\nMessages: %d\nHas Tool Calls: %v", messageCount, hasToolCalls)
}

// LogSystemInfo logs system information
func LogSystemInfo(info map[string]string) {
	InfoLogger.Printf("SYSTEM INFO: %+v", info)
}

// LogStartup logs application startup information
func LogStartup(config map[string]string) {
	// Redact sensitive information
	if apiKey, ok := config["llm_api_key"]; ok && apiKey != "" {
		config["llm_api_key"] = "[REDACTED]"
	}
	if apiKey, ok := config["congress_api_key"]; ok && apiKey != "" {
		config["congress_api_key"] = "[REDACTED]"
	}

	InfoLogger.Printf("APPLICATION STARTUP: %+v", config)
}

// LogToolCallSequence logs a new tool call to the sequence
func LogToolCallSequence(id, name, args string) {
	record := ToolCallRecord{
		ID:        id,
		Name:      name,
		Args:      args,
		Timestamp: time.Now(),
	}

	toolCallSequence = append(toolCallSequence, record)

	// Log the tool call
	recordJSON, _ := json.MarshalIndent(record, "", "  ")
	ToolLogger.Printf("TOOL CALL SEQUENCE - NEW CALL:\n%s", string(recordJSON))
}

// LogToolCallResponse logs a response to a tool call in the sequence
func LogToolCallResponse(id, response string, err error) {
	// Find the tool call in the sequence
	for i, record := range toolCallSequence {
		if record.ID == id {
			// Update the record
			toolCallSequence[i].Response = response
			if err != nil {
				toolCallSequence[i].Error = err.Error()
			}

			// Log the updated record
			recordJSON, _ := json.MarshalIndent(toolCallSequence[i], "", "  ")
			ToolLogger.Printf("TOOL CALL SEQUENCE - RESPONSE:\n%s", string(recordJSON))
			break
		}
	}
}

// LogToolSelectionReasoning logs the reasoning behind a tool selection
func LogToolSelectionReasoning(query, selectedTool, reasoning string) {
	DecisionLogger.Printf("TOOL SELECTION:\nQuery: %s\nSelected Tool: %s\nReasoning: %s",
		truncateForLog(query, 500), selectedTool, truncateForLog(reasoning, 1000))
}

// LogToolCallSummary logs a summary of all tool calls in the current sequence
func LogToolCallSummary() {
	if len(toolCallSequence) == 0 {
		ToolLogger.Printf("TOOL CALL SUMMARY: No tool calls in this session")
		return
	}

	summary := fmt.Sprintf("TOOL CALL SUMMARY (%d calls):\n", len(toolCallSequence))
	for i, record := range toolCallSequence {
		status := "Completed"
		if record.Response == "" && record.Error == "" {
			status = "Pending"
		} else if record.Error != "" {
			status = "Error"
		}

		summary += fmt.Sprintf("%d. %s (%s) - Status: %s\n",
			i+1, record.Name, record.ID, status)
	}

	ToolLogger.Print(summary)
}

// ResetToolCallSequence resets the tool call sequence
func ResetToolCallSequence() {
	toolCallSequence = make([]ToolCallRecord, 0)
	ToolLogger.Printf("TOOL CALL SEQUENCE RESET")
}

// LogDetailedLLMInteraction logs detailed information about an LLM interaction
func LogDetailedLLMInteraction(requestType string, details map[string]interface{}) {
	detailsJSON, _ := json.MarshalIndent(details, "", "  ")
	LLMLogger.Printf("DETAILED %s:\n%s", requestType, string(detailsJSON))
}
