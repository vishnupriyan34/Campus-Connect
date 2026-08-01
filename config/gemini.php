<?php
// Gemini API Integration Helper

require_once __DIR__ . '/db.php'; // Ensure env is loaded

function getResumeMatchScore($resumePath, $jobDescription) {
    $apiKey = getenv('GEMINI_API_KEY');
    
    // Check if key is not configured or placeholder
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
        return [
            'success' => false,
            'message' => 'Gemini API key is not configured.',
            'match_score' => 0,
            'missing_skills' => [],
            'suggestions' => 'Please configure the GEMINI_API_KEY in the .env file.'
        ];
    }
    
    if (!file_exists($resumePath)) {
        return [
            'success' => false,
            'message' => 'Resume PDF file not found.',
            'match_score' => 0,
            'missing_skills' => [],
            'suggestions' => 'Please upload a valid resume PDF first.'
        ];
    }
    
    // Read and base64 encode PDF
    $pdfData = base64_encode(file_get_contents($resumePath));
    
    // Setup prompt instruction
    $prompt = "You are a professional recruiting assistant. Analyze the uploaded resume PDF against the following job/drive description: \n\n" . 
              "[Job Description]\n" . $jobDescription . "\n\n" .
              "Evaluate how well the student matches the requirements. Return a JSON object with EXACTLY the following keys:\n" .
              "1. \"match_score\": An integer between 0 and 100 representing the match percentage.\n" .
              "2. \"missing_skills\": A JSON array of string skill tags that are required in the job description but missing or weak in the resume (e.g. [\"Docker\", \"AWS\"]). Keep it to a maximum of 5 most critical skills.\n" .
              "3. \"suggestions\": A brief single-sentence feedback statement on how the student can improve their profile for this drive.\n\n" .
              "Response MUST be valid JSON only. Do not wrap in markdown blocks like ```json.";

    // API Payload
    $payload = [
        "contents" => [
            [
                "parts" => [
                    [
                        "inlineData" => [
                            "mimeType" => "application/pdf",
                            "data" => $pdfData
                        ]
                    ],
                    [
                        "text" => $prompt
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "responseMimeType" => "application/json"
        ]
    ];
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // Disable SSL verification if needed for local curl issues on Windows (optional, but let's keep it secure by default)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false,
            'message' => 'Gemini API call failed with HTTP code ' . $httpCode,
            'match_score' => 0,
            'missing_skills' => [],
            'suggestions' => 'Could not connect to Gemini AI. Check network settings and API key.'
        ];
    }
    
    try {
        $result = json_decode($response, true);
        $textResult = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Clean JSON response (in case Gemini returned markdown code block despite generationConfig)
        $textResult = trim($textResult);
        if (strpos($textResult, '```') === 0) {
            $textResult = preg_replace('/^```(?:json)?|```$/m', '', $textResult);
            $textResult = trim($textResult);
        }
        
        $data = json_decode($textResult, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON returned by Gemini API');
        }
        
        return [
            'success' => true,
            'match_score' => intval($data['match_score'] ?? 0),
            'missing_skills' => $data['missing_skills'] ?? [],
            'suggestions' => $data['suggestions'] ?? ''
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error parsing AI response: ' . $e->getMessage(),
            'match_score' => 0,
            'missing_skills' => [],
            'suggestions' => 'Error reading AI recommendations.'
        ];
    }
}
?>
