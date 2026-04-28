# Prompt Codes

Use this list to create the placeholders. Below is a list for each question type with an example prompt as well as filled in with example data.

**In the following examples the question always has the question code `exampleQuestion`.**

If no answer was selected for a question, `"N/A"` is inserted at the placeholder. Empty text fields however are inserted as an empty string.

- [Single Choice Questions](#single-choice-questions)
- [Arrays](#arrays)
- [Multiple Choice Questions](#multiple-choice-questions)
- [Text Questions](#text-questions)
- [Mask Questions](#mask-questions)
- [Meta Fields](#meta-fields)

## Single Choice Questions

### 5 Point Choice

Prompt:
```
A user evaluated this item with a score of [exampleQuestion] / 5.
```

The user selected the number 3:
```
A user evaluated this item with a score of 3 / 5.
```

### Bootstrap Buttons, Bootstrap Dropdown, List (Dropdown), List (Radio), List with Comment

Prompt:
```
A user chose [exampleQuestion] as their favorite color.
```

The user selected the item which read "green":
```
A user chose green as their favorite color.
```

#### 'Other' Field

Access the 'other'-field by appending '_other' to the question code.

Prompt:
```
A user chose [exampleQuestion][exampleQuestion_other] as their favorite color.
```

The user selected the option 'other' and wrote "yellow":
```
A user chose yellow as their favorite color.
```

#### List with Comment - 'Comment' Field

Prompt:
```
A user wrote '[exampleQuestion_comment]' as a comment.
```

The user wrote "Green matches my vibe" as their comment:
```
A user wrote 'Green matches my vibe' as a comment.
```

## Arrays

### Array, Array by Column

*This is an example for Array. Array by column works the same, however you might need to switch subquestions and answer options and rewrite the prompt.*

Subquestions: `sq01`: red, `sq02`: green<br>
Answer options: `ans1`: I like it, `ans2`: I don't mind it, `ans3`: I dislike it

Prompt:
```
A user was presented colors. To the color red they said '[exampleQuestion_sq01]' and to the color green they said '[exampleQuestion_sq02]'.
```

The user said they liked green and didn't mind red:
```
A user was presented colors. To the color red they said 'I don't mind it' and to the color green they said 'I like it'.
```

### Array (5 Point Choice), Array (10 Point Choice)

*This is an example for Array (5 Point Choice). Array (10 Point Choice) works the same but the numbers can go up to 10.*

Subquestions: `sq01`: red, `sq02`: green<br>

Prompt:
```
A user was presented colors. They rated the color red [exampleQuestion_sq01] / 5 and the color green [exampleQuestions_sq02] / 5.
```

The user rated green with a 5 and red with a 2:
```
A user was presented colors. They rated the color red 2 / 5 and the color green 5 / 5.
```

### Array (Increase / Same / Decrease), Array (Yes / No / Uncertain)

*This is an example for Array (Increase / Same / Decrease). Array (Yes / No / Uncertain) works the same.*

Subquestions: `sq01`: Happiness, `sq02`: Luck

Prompt:
```
A user evaluated some values and selected Happiness - [exampleQuestion_sq01] and Luck - [exampleQuestion-sq02].
```

The user selected the field increase for both Happiness and Luck:
```
A user evaluated some values and selected Happiness - Increase and Luck - Increase.
```

### Array (Numbers), Array (Texts)

*This is an example for Array (Numbers). Array (Texts) works the same.*

Y-Scale: `sqY01`: Google, `sqY02`: Bing<br>
X-Scale: `sqX01`: Speed, `sqX02`: Design

Prompt:
```
In terms of speed the user gave google the score [exampleQuestion_sqY01_sqX01] and bing [exampleQuestion_sqY02_sqX01]. In terms of design the user gave google the score [exampleQuestion_sqY01_sqX02] and bing [exampleQuestion_sqY02_sqX02].
```

The user gave google a 4 for speed and a 5 for design and bing a 5 for speed and a 3 for design:
```
In terms of speed the user gave google the score 4 and bing 5. In terms of design the user gave google the score 5 and bing 3.
```

### Array Dual Scale

To get the answer to the subquestion for the first scale, simply use the structure `[qCode_sqCode]`.
However the answer to the second scale is stored in a completely different format. To access it use the following structure:
`[{surveyId}X{questionGroupId}X{questionId}{subquestionCode}#1]`

- surveyId is the 6 digit number of the survey
- questionGroupId and questionId can be found when clicking on the question in the structure tab of the survey
- subquestionCode is the code you'd normally use
- the `#1` is always there when accessing the second scale

Subquestions: `sq01`: green, `sq02`: red<br>
Answer Scale 1: `ans1`: like, `ans2`: dislike<br>
Answer Scale 2: `ans1`: bright, `ans2`: dark

Prompt:
```
A user says they [exampleQuestion_sq01] the color green and they find it [592886X51X517sq01#1]. They also say they [exampleQuestion_sq02] the color red and they think it is a [592886X51X517sq02#1] color.
```

The user liked the color green and thought it was a bright color and they disliked the color red and said it was a rather dark color:
```
A user says they like the color green and they find it bright. They also say they dislike the color red and they think it is a dark color.
```

## Multiple Choice Questions

### Bootstrap Buttons, Multiple Choice, Multiple Choice with Comments

Subquestions: `sq01`: red, `sq02`: green, `sq03`: blue

Prompt:
```
A user was presented multiple colors and had to evaluate whether they liked them or not:
red: [exampleQuestion_sq01]
green: [exampleQuestion_sq02]
blue: [exampleQuestion_sq03]
```

The user selected green and blue:
```
A user was presented multiple colors and had to evaluate whether they liked them or not:
red: No
green: Yes
blue: Yes
```

#### 'Other' Field

Access the 'other'-field by appending '_other' to the question code.

Prompt:
```
From this list the user chose red: [exampleQuestion_sq01], green: [exampleQuestion_sq02]. They had the choice to define their own color, in this field they wrote '[exampleQuestion_other]'.
```

The user selected the options "green" and wrote "yellow" in the 'other' field:
```
From this list the user chose red: No, green: Yes. They had the choice to define their own color, in this field they wrote 'yellow'.
```

#### Multiple Choice with Comments - 'Comment' Field

Prompt:
```
When evaluating the color red the user wrote '[exampleQuestion_sq01comment]'.
```

The user wrote "Stop asking me about colors!" as their comment to the color red:
```
When evaluating the color red the user wrote 'Stop asking me about colors!'.
```

## Text Questions

### Huge Free Text, Long Free Text, Short Free Text

Prompt:
```
The favorite food of this user is [exampleQuestion].
```

The user wrote "Spaghetti" as their favorite food.
```
The favorite food of this user is Spaghetti.
```

### Input on Demand, Multiple Short Text

Subquestions: `sq01`: Your favorite food, `sq02`: Your favorite color

Prompt:
```
The user said [exampleQuestion_sq01] was their favorite food and [exampleQuestion_sq02] was their favorite color.
```

The user wrote "Spaghetti" and "green":
```
The user said Spaghetti was their favorite food and green was their favorite color.
```

## Mask Questions

### Date / Time

Prompt:
```
The last time the user ate their favorite food was on [exampleQuestion].
```

The user chose the date 11-23-2025:
```
The last time the user ate their favorite food was on 11-23-2025.
```

### Gender

Prompt:
```
The users gender is [exampleQuestion].
```

The user selected Female as their gender:
```
The users gender is Female.
```

### Multiple Numerical Input

Subquestions: `sq01`: books read, `sq02`: movies watched

Prompt:
```
The user stated they read [exampleQuestion_sq01] books and watched [exampleQuestion_sq02] movies in the past year.
```

The user read 3 books and watched 15 movies.
```
The user stated they read 3 books and watched 15 movies in the past year.
```

### Numerical Input

Prompt:
```
The user is [exampleQuestion] years old.
```

The user wrote 37:
```
The user is 37 years old.
```

### Ranking, Ranking Advanced

Answer options: `ans1`: red, `ans2`: green

Prompt:
```
The user was presented colors and had to rank them. They chose [exampleQuestion_1] as rank 1 and [exampleQuestion_2] as rank 2.
```

The user put the color green at position 1 and the color red at position 2:
```
The user was presented colors and had to rank them. They chose green as rank 1 and red as rank 2.
```

### Yes / No

Prompt:
```
The user was asked if they would participate in this study again. Their answer was [exampleQuestion].
```

The user selected No.
```
The user was asked if they would participate in this study again. Their answer was No.
```

## Meta Fields

Every survey has a field `[startlanguage]` which stores the language the user has selected for the survey (ISO-639-1 (e.g., "en", "de", "fr")). It can also be used in a prompt.

Prompt:
```
The current language of this survey is [startlanguage].
```

The user started the survey in english:
```
The current language of this survey is en.
```
